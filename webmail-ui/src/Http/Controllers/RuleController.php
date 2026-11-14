<?php
declare(strict_types=1);

namespace Webmail\Http\Controllers;

use Webmail\App;
use Webmail\Core\{Request, Response};
use Webmail\Mail\RuleSpec;
use Webmail\Support\Url;
use Webmail\WildDuck\{ApiException, Ids};
use Webmail\WildDuck\Model\Rule;

final class RuleController
{
    public static function index(Request $r, App $app): Response
    {
        return self::indexPage($app);
    }

    public static function newForm(Request $r, App $app): Response
    {
        return self::formPage($app, null, [], []);
    }

    public static function editForm(Request $r, App $app): Response
    {
        $rule = self::find($app, $r->params['id']);
        if (!$rule->isSimple() || $rule->blockedPattern() !== null) {
            $app->session->flash('info', 'This rule uses options that can only be managed by your mail administrator. You can still turn it off or delete it.');
            return Response::redirect('/rules');
        }
        return self::formPage($app, $rule, RuleSpec::toForm($rule), []);
    }

    public static function create(Request $r, App $app): Response
    {
        [$spec, $errors] = self::spec($app, $r);
        if ($spec !== null && ($full = self::quotaError($app)) !== null) {
            $spec = null;
            $errors[] = $full;
        }
        if ($spec === null) {
            return self::formPage($app, null, $r->post, $errors, 422);
        }
        try {
            $app->filters()->create($spec);
        } catch (ApiException $e) {
            return self::refused($app, $e, fn (array $x) => self::formPage($app, null, $r->post, $x, 422));
        }
        $app->session->flash('success', 'Rule “' . $spec['name'] . '” created. It applies to new incoming messages.');
        return Response::redirect('/rules');
    }

    public static function update(Request $r, App $app): Response
    {
        $rule = self::find($app, $r->params['id']);
        if (!$rule->isSimple() || $rule->blockedPattern() !== null) {
            throw new ApiException('Rule not found.', 404, 'FilterNotFound');
        }
        [$spec, $errors] = self::spec($app, $r);
        if ($spec === null) {
            return self::formPage($app, $rule, $r->post, $errors, 422);
        }
        try {
            $app->filters()->update($rule->id, $spec);
        } catch (ApiException $e) {
            return self::refused($app, $e, fn (array $x) => self::formPage($app, $rule, $r->post, $x, 422));
        }
        $app->session->flash('success', 'Rule saved.');
        return Response::redirect('/rules');
    }

    public static function toggle(Request $r, App $app): Response
    {
        $rule = self::find($app, $r->params['id']);
        try {
            $app->filters()->setDisabled($rule->id, !$rule->disabled);
        } catch (ApiException $e) {
            return self::refused($app, $e, fn (array $x) => self::indexPage($app, $x, 422));
        }
        $app->session->flash('success', $rule->disabled ? 'Rule turned on.' : 'Rule turned off.');
        return Response::redirect('/rules');
    }

    public static function confirmDelete(Request $r, App $app): Response
    {
        $rule = self::find($app, $r->params['id']);
        $d = RuleSpec::describe($rule, self::byId($app->folders()), self::byId($app->labels()));
        return $app->page('rules.delete', 'Delete rule', 'manage-rules', ['rule' => $rule, 'desc' => $d]);
    }

    public static function delete(Request $r, App $app): Response
    {
        $rule = self::find($app, $r->params['id']);
        try {
            $app->filters()->delete($rule->id);
        } catch (ApiException $e) {
            return self::refused($app, $e, fn (array $x) => self::indexPage($app, $x, 422));
        }
        $app->session->flash('success', $rule->blockedPattern() !== null ? 'Unblocked ' . $rule->blockedPattern() . '.' : 'Rule deleted.');
        return Response::redirect('/rules');
    }

    // ---- blocked senders ----------------------------------------------------------------

    public static function block(Request $r, App $app): Response
    {
        $res = self::createBlock($app, $r->string('sender'));
        if ($res['error'] !== null) {
            return self::indexPage($app, [$res['error']], 422, $r->string('sender'));
        }
        $app->session->flash($res['created'] ? 'success' : 'info', $res['created'] ? 'Blocked ' . $res['pattern'] . '. New mail from it goes to Spam.' : $res['pattern'] . ' is already blocked.');
        return Response::redirect('/rules');
    }

    /** One-click "Block sender" from a message: blocks the sender and moves that message to Spam. */
    public static function quickBlock(Request $r, App $app): Response
    {
        $back = Url::safeReturn($r->string('return') ?: null, '/rules');
        $res = self::createBlock($app, $r->string('sender'));
        if ($res['error'] !== null) {
            $app->session->flash('error', $res['error']);
            return Response::redirect($back);
        }
        $moved = false;
        $mb = $r->string('src_mailbox');
        $uid = $r->string('src_id');
        $junk = MailController::special($app->folders(), '\\Junk');
        if (Ids::objectId($mb) && Ids::uid($uid) && $junk !== null && $junk->id !== $mb) {
            foreach ($app->folders() as $f) {
                if ($f->id === $mb) {
                    try {
                        $app->messages()->update($mb, $uid, ['moveTo' => $junk->id]);
                        $moved = true;
                    } catch (ApiException $e) {
                        if ($e->isUnauthorized()) {
                            throw $e;
                        }
                    }
                }
            }
        }
        $app->session->flash('success', ($res['created'] ? 'Blocked ' . $res['pattern'] . '.' : $res['pattern'] . ' was already blocked.') . ($moved ? ' The message was moved to Spam.' : ''));
        return Response::redirect($moved ? '/mail/spam' : $back);
    }

    /** @return array{created:bool,pattern:string,error:?string} */
    private static function createBlock(App $app, string $raw): array
    {
        $pattern = RuleSpec::blockPattern($raw);
        if ($pattern === null) {
            return ['created' => false, 'pattern' => '', 'error' => 'Enter an email address (name@example.com) or a domain (example.com).'];
        }
        $own = strtolower($app->auth->user()['address'] ?? '');
        $ownDomain = '@' . (substr(strrchr($own, '@') ?: '', 1));
        foreach ($app->identities() ?: [] as $i) {
            if (strcasecmp($i->address, $pattern) === 0) {
                return ['created' => false, 'pattern' => $pattern, 'error' => 'You cannot block your own address.'];
            }
        }
        if ($pattern === $own || $pattern === $ownDomain) {
            return ['created' => false, 'pattern' => $pattern, 'error' => 'You cannot block your own address or domain.'];
        }
        try {
            $all = $app->filters()->all();
            foreach ($all['rules'] as $rule) {
                if ($rule->blockedPattern() === $pattern) {
                    return ['created' => false, 'pattern' => $pattern, 'error' => null];
                }
            }
            if ($all['allowed'] > 0 && $all['used'] >= $all['allowed']) {
                return ['created' => false, 'pattern' => $pattern, 'error' => 'You have used all ' . $all['allowed'] . ' rules your account allows. Delete one first.'];
            }
            $app->filters()->create(['name' => Rule::BLOCK_PREFIX . $pattern, 'query' => ['from' => $pattern], 'action' => ['spam' => true]]);
        } catch (ApiException $e) {
            if ($e->isUnauthorized() || $e->isUnavailable()) {
                throw $e;
            }
            error_log('[webmail] block refused status=' . $e->httpStatus . ' code=' . $e->apiCode);
            return ['created' => false, 'pattern' => $pattern, 'error' => 'The mail server did not accept the change, so nothing was saved.'];
        }
        return ['created' => true, 'pattern' => $pattern, 'error' => null];
    }

    // ---- shared -------------------------------------------------------------------------

    /** @return array{0:?array,1:list<string>} */
    private static function spec(App $app, Request $r): array
    {
        $out = RuleSpec::fromInput($r->post, self::byId($app->folders()), self::byId($app->labels()));
        return [$out['spec'], $out['errors']];
    }

    private static function quotaError(App $app): ?string
    {
        try {
            $all = $app->filters()->all();
        } catch (ApiException $e) {
            if ($e->isUnauthorized() || $e->isUnavailable()) {
                throw $e;
            }
            return null;
        }
        return $all['allowed'] > 0 && $all['used'] >= $all['allowed'] ? 'You have used all ' . $all['allowed'] . ' rules your account allows. Delete one first.' : null;
    }

    /** Only rules that WildDuck lists for this account can be addressed. */
    private static function find(App $app, string $id): Rule
    {
        Ids::require($id, 'object');
        foreach ($app->filters()->all()['rules'] as $rule) {
            if ($rule->id === $id) {
                return $rule;
            }
        }
        throw new ApiException('Rule not found.', 404, 'FilterNotFound');
    }

    /** @template T of object @param list<T> $list @return array<string,T> */
    private static function byId(array $list): array
    {
        $o = [];
        foreach ($list as $x) {
            $o[$x->id] = $x;
        }
        return $o;
    }

    private static function refused(App $app, ApiException $e, callable $render): Response
    {
        if ($e->isUnauthorized() || $e->isUnavailable() || $e->isNotFound()) {
            throw $e;
        }
        error_log('[webmail] rules save refused status=' . $e->httpStatus . ' code=' . $e->apiCode);
        return $render(['The mail server did not accept the change, so nothing was saved.' . ($e->httpStatus === 403 ? ' This account may not be allowed to change rules.' : '')]);
    }

    /** @param list<string> $errors */
    private static function indexPage(App $app, array $errors = [], int $status = 200, string $senderValue = ''): Response
    {
        $rules = [];
        $blocked = [];
        $allowed = 0;
        $used = 0;
        $unavailable = false;
        try {
            $all = $app->filters()->all();
            $allowed = $all['allowed'];
            $used = $all['used'];
            foreach ($all['rules'] as $rule) {
                $rule->blockedPattern() !== null ? $blocked[] = $rule : $rules[] = $rule;
            }
        } catch (ApiException $e) {
            if ($e->isUnauthorized()) {
                throw $e;
            }
            $unavailable = true;
        }
        usort($blocked, static fn (Rule $a, Rule $b): int => strcasecmp((string) $a->blockedPattern(), (string) $b->blockedPattern()));
        $folders = self::byId($app->folders());
        $labels = self::byId($app->labels());
        $descriptions = [];
        foreach ($rules as $x) {
            $descriptions[$x->id] = RuleSpec::describe($x, $folders, $labels);
        }
        $res = $app->page('rules.index', 'Rules', 'manage-rules', [
            'rules' => $rules, 'blocked' => $blocked, 'allowed' => $allowed, 'used' => $used, 'unavailable' => $unavailable, 'errors' => $errors,
            'senderValue' => $senderValue, 'descriptions' => $descriptions,
        ]);
        $res->status = $status;
        return $res;
    }

    /** @param ?Rule $rule null = new @param array<string,mixed> $values @param list<string> $errors */
    private static function formPage(App $app, ?Rule $rule, array $values, array $errors, int $status = 200): Response
    {
        $res = $app->page('rules.form', $rule ? 'Edit rule' : 'New rule', 'manage-rules', ['rule' => $rule, 'v' => $values, 'errors' => $errors]);
        $res->status = $status;
        return $res;
    }
}
