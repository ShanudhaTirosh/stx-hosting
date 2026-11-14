<?php
declare(strict_types=1);

namespace Webmail\Http\Controllers;

use Webmail\App;
use Webmail\Core\{Env, Request, Response};
use Webmail\Mail\{AddressParser, ComposeForm, HtmlText, Prefill, Uploads};
use Webmail\Support\HtmlSanitizer;
use Webmail\WildDuck\Model\{Identity, MessageDetail};
use Webmail\WildDuck\{ApiException, Ids};

final class ComposeController
{
    public static function blank(Request $r, App $app): Response
    {
        $f = new ComposeForm();
        $f->html = self::withSignature($app, '<p><br></p>');
        $to = AddressParser::parse(mb_substr($r->string('to'), 0, 500));
        $f->to = AddressParser::format($to['list']);
        $f->subject = self::oneLine($r->string('subject'));
        return self::render($app, $f);
    }

    public static function reply(Request $r, App $app): Response
    {
        $m = self::source($r, $app);
        $f = Prefill::reply($m, $app->auth->user()['address'] ?? '', false);
        $f->from = self::pickFrom($app, $m);
        $f->html = self::withSignature($app, $f->html);
        return self::render($app, $f);
    }

    public static function replyAll(Request $r, App $app): Response
    {
        $m = self::source($r, $app);
        $f = Prefill::reply($m, $app->auth->user()['address'] ?? '', true);
        $f->from = self::pickFrom($app, $m);
        $f->html = self::withSignature($app, $f->html);
        return self::render($app, $f);
    }

    public static function forward(Request $r, App $app): Response
    {
        $m = self::source($r, $app);
        $f = Prefill::forward($m);
        $f->from = self::pickFrom($app, $m);
        $f->html = self::withSignature($app, $f->html);
        return self::render($app, $f);
    }

    public static function edit(Request $r, App $app): Response
    {
        $m = self::source($r, $app);
        if (!$m->draft) {
            return Response::redirect('/mail/' . rawurlencode($r->params['box']) . '/' . $m->id);
        }
        $f = Prefill::draft($m);
        foreach ($app->identities() as $i) {
            if (strcasecmp($i->address, $m->fromAddress) === 0) {
                $f->from = $i->address;
            }
        }
        return self::render($app, $f);
    }

    public static function submit(Request $r, App $app): Response
    {
        $asDraft = $r->string('do') === 'draft';
        $own = $app->auth->user()['address'] ?? '';
        $folders = $app->folders();
        // The From address must be one of the account's own addresses; anything else falls back to the main address.
        $profileName = $app->profileInfoOrNull()['name'] ?? '';
        $chosen = trim($r->string('from'));
        $pick = null;
        foreach ($app->identities() as $i) {
            if ($chosen !== '' ? strcasecmp($i->address, $chosen) === 0 : $i->main) {
                $pick = $i;
                break;
            }
        }
        $from = ['name' => $pick !== null && $pick->name !== '' ? $pick->name : $profileName, 'address' => $pick?->address ?? $own];
        $from['name'] = \Webmail\Mail\Prefs::clean($from['name']);
        $errors = [];

        $form = new ComposeForm(
            mb_substr($r->string('to'), 0, 5000), mb_substr($r->string('cc'), 0, 5000), mb_substr($r->string('bcc'), 0, 5000),
            self::oneLine($r->string('subject')), '', null, null, null, [], preg_replace('/[^a-f0-9]/', '', $r->string('compose_id')) ?: '', $r->string('mode') ?: 'new',
        );

        // Body: editor HTML (or the no-JS fallback), sanitised for sending.
        $rawHtml = $r->string('html');
        if (trim(strip_tags($rawHtml)) === '' && !str_contains($rawHtml, '<img') && $r->string('text_fallback') !== '') {
            $rawHtml = '<p>' . nl2br(e($r->string('text_fallback')), false) . '</p>';
        }
        if (strlen($rawHtml) > Env::int('MAX_MESSAGE_MB', 25) * 1048576) {
            $errors[] = 'The message text is too large.';
            $rawHtml = '';
        }
        $html = HtmlSanitizer::cleanOutgoing($rawHtml);
        $form->html = $html;

        // References are only honoured when they point at messages in mailboxes this account owns.
        $mid = static fn (string $k): ?array => Ids::objectId($r->string($k . '_mailbox')) && Ids::uid($r->string($k . '_id'))
            ? ['mailbox' => $r->string($k . '_mailbox'), 'id' => (int) $r->string($k . '_id')] : null;
        $owned = static function (?array $ref) use ($folders): ?array {
            foreach ($folders as $f) {
                if ($ref !== null && $f->id === $ref['mailbox']) {
                    return $ref;
                }
            }
            return null;
        };
        $draft = $owned($mid('draft'));
        $source = $owned($mid('src'));
        $reference = $owned($mid('ref'));
        $action = $r->string('ref_action');
        $reference = $reference !== null && in_array($action, ['reply', 'replyAll', 'forward'], true) ? $reference + ['action' => $action] : null;
        $form->from = $pick !== null && !$pick->main ? $pick->address : '';
        $form->draft = $draft;
        $form->reference = $reference;
        $form->source = $source;

        // A draft reference makes WildDuck DELETE that message after sending, so it must really be a draft in Drafts.
        if ($draft !== null) {
            $drafts = MailController::special($folders, '\\Drafts');
            $isDraft = false;
            if ($drafts !== null && $drafts->id === $draft['mailbox']) {
                try {
                    $isDraft = $app->messages()->get($draft['mailbox'], (string) $draft['id'], false)->draft;
                } catch (ApiException) {
                    $isDraft = false;
                }
            }
            if (!$isDraft) {
                $draft = null;
                $form->draft = null;
            }
        }

        $to = AddressParser::parse($form->to);
        $cc = AddressParser::parse($form->cc);
        $bcc = AddressParser::parse($form->bcc);
        foreach (['To' => $to, 'Cc' => $cc, 'Bcc' => $bcc] as $label => $p) {
            if ($p['invalid']) {
                $errors[] = "$label has an invalid address: " . implode(', ', $p['invalid']);
            }
        }
        $count = count($to['list']) + count($cc['list']) + count($bcc['list']);
        if ($count > AddressParser::MAX) {
            $errors[] = 'A message can have at most ' . AddressParser::MAX . ' recipients.';
        }
        if (!$asDraft && $count === 0 && !$errors) {
            $errors[] = 'Add at least one recipient.';
        }

        // Attachments: carried over from the source message, plus new uploads.
        $maxFile = Env::int('MAX_ATTACHMENT_MB', 20) * 1048576;
        $maxTotal = Env::int('MAX_MESSAGE_MB', 25) * 1048576;
        $attachments = [];
        $bytes = 0;
        $keepIds = array_values(array_filter((array) ($r->post['keep'] ?? []), static fn ($v) => is_string($v) && Ids::attachment($v)));
        if ($source !== null) {
            $srcMsg = null;
            try {
                $srcMsg = $app->messages()->get($source['mailbox'], (string) $source['id'], false);
                $form->carry = [];
                foreach (Prefill::forward($srcMsg)->carry as $c) {
                    $c['keep'] = in_array($c['id'], $keepIds, true);
                    $form->carry[] = $c;
                }
            } catch (ApiException) {
                $errors[] = 'The original message could not be loaded to copy its attachments.';
            }
            if ($srcMsg !== null && !$errors) {
                foreach ($form->carry as $c) {
                    if (!$c['keep']) {
                        continue;
                    }
                    if ($c['size'] > $maxFile || $bytes + $c['size'] > $maxTotal) {
                        $errors[] = "“{$c['filename']}” is too large to include.";
                        continue;
                    }
                    $bytes += $c['size'];
                    $file = $app->messages()->attachment($source['mailbox'], (string) $source['id'], $c['id']);
                    $type = strtolower(trim(explode(';', $file['contentType'])[0]));
                    $attachments[] = ['filename' => Uploads::cleanName($c['filename']), 'contentType' => $type !== '' ? $type : 'application/octet-stream', 'content' => base64_encode($file['body'])];
                }
            }
        }
        $up = Uploads::collect($_FILES, $maxFile, $maxTotal, $bytes);
        array_push($errors, ...$up['errors']);
        foreach ($up['attachments'] as $a) {
            $attachments[] = ['filename' => $a['filename'], 'contentType' => $a['contentType'], 'content' => $a['content']];
        }

        // Double-submit protection (a refresh or double click must not send twice).
        $sent = (array) $app->session->get('sent_ids', []);
        if (!$asDraft && $form->composeId !== '' && in_array($form->composeId, $sent, true)) {
            $app->session->flash('info', 'That message was already sent.');
            return Response::redirect('/mail/sent');
        }

        if ($errors) {
            return self::render($app, $form, $errors, 422, $folders, $r->string('sig'));
        }

        try {
            $app->submitter()->submit(
                $from, $to['list'], $cc['list'], $bcc['list'], $form->subject, $html, HtmlText::toText($html),
                $attachments, $asDraft, $asDraft ? null : $reference, $draft,
            );
        } catch (ApiException $e) {
            if ($e->isUnauthorized()) {
                throw $e;
            }
            error_log('[webmail] submit failed status=' . $e->httpStatus . ' code=' . $e->apiCode);
            $msg = $e->isUnavailable() ? 'The mail server is unavailable. Your message was not sent; nothing was lost, so you can try again.' : $e->userMessage();
            $note = $attachments !== [] && $up['attachments'] !== [] ? ' Please attach any new files again.' : '';
            return self::render($app, $form, [$msg . $note], $e->isUnavailable() ? 503 : 422, $folders, $r->string('sig'));
        }

        if ($asDraft) {
            $app->session->flash('success', 'Draft saved.');
            return Response::redirect('/mail/drafts');
        }
        $sent[] = $form->composeId;
        $app->session->put('sent_ids', array_slice($sent, -20));
        $app->session->flash('success', 'Message sent.');
        return Response::redirect('/mail/sent');
    }

    /** Reply from the address the message was sent to, when that is one of our aliases ('' keeps the main address). */
    private static function pickFrom(App $app, MessageDetail $m): string
    {
        $idents = $app->identities();
        foreach (array_merge($m->to, $m->cc) as $a) {
            foreach ($idents as $i) {
                if (!$i->main && strcasecmp($i->address, $a['address']) === 0) {
                    return $i->address;
                }
            }
        }
        return '';
    }

    /** Adds the default signature to a fresh body (new / reply / forward). Never fails the page. */
    private static function withSignature(App $app, string $html): string
    {
        $p = $app->prefsOrNull();
        return $p === null ? $html : \Webmail\Mail\Prefs::insertSignature($html, $p->defaultSignatureHtml());
    }

    /** @param list<string> $errors */
    private static function render(App $app, ComposeForm $f, array $errors = [], int $status = 200, ?array $folders = null, ?string $sig = null): Response
    {
        $prefs = $app->prefsOrNull();
        $sigs = $prefs?->signatures ?? [];
        $selected = $sig ?? (in_array($f->mode, ['new', 'reply', 'replyAll', 'forward'], true) ? ($prefs?->defaultSignature ?? '') : '');
        if ($selected !== '' && !in_array($selected, array_column($sigs, 'id'), true)) {
            $selected = '';
        }
        $idents = $app->identities();
        $res = $app->page('compose', $f->heading(), '', [
            'identities' => $idents,
            'signatures' => $sigs,
            'sigSelected' => $selected,
            'f' => $f,
            'errors' => $errors,
            'maxFileMb' => Env::int('MAX_ATTACHMENT_MB', 20),
        ], $folders, '', ['editor', 'compose', 'suggest']);
        $res->status = $status;
        return $res;
    }

    private static function source(Request $r, App $app): \Webmail\WildDuck\Model\MessageDetail
    {
        $uid = Ids::require($r->params['uid'] ?? '', 'uid');
        $box = MailController::boxById($app->folders(), $r->params['box']);
        return $app->messages()->get($box->id, $uid, false);
    }

    private static function oneLine(string $s): string
    {
        return mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $s) ?? ''), 0, 998);
    }
}
