<?php
declare(strict_types=1);

namespace Webmail\Mail;

use Webmail\WildDuck\Model\{Label, Mailbox, Rule};

/** Turns a rule form into a validated WildDuck filter, and describes existing rules in words. */
final class RuleSpec
{
    public const MAX_NAME = 100;
    public const MAX_VALUE = 200;
    public const MAX_TEXT = 500;
    public const MAX_LABELS = 10;

    /**
     * @param array<string,mixed> $in raw form input
     * @param array<string,Mailbox> $folders by id
     * @param array<string,Label> $labels by id
     * @return array{spec:?array{name:string,query:array<string,mixed>,action:array<string,mixed>},errors:list<string>}
     */
    public static function fromInput(array $in, array $folders, array $labels): array
    {
        $errors = [];
        $str = static fn (string $k, int $max): string => is_string($in[$k] ?? null) ? mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $in[$k]) ?? ''), 0, $max + 1) : '';

        $query = [];
        foreach (['from' => 'from', 'to' => 'to', 'subject' => 'subject', 'list_id' => 'listId'] as $field => $key) {
            $v = $str($field, self::MAX_VALUE);
            if (mb_strlen($v) > self::MAX_VALUE) {
                $errors[] = 'Each condition may have up to ' . self::MAX_VALUE . ' characters.';
            } elseif ($v !== '') {
                $query[$key] = $v;
            }
        }
        $text = $str('text', self::MAX_TEXT);
        if (mb_strlen($text) > self::MAX_TEXT) {
            $errors[] = 'The text to look for may have up to ' . self::MAX_TEXT . ' characters.';
        } elseif ($text !== '') {
            $query['text'] = $text;
        }
        if (($in['has_attachment'] ?? '') === '1') {
            $query['ha'] = true;
        }
        $op = (string) ($in['size_op'] ?? '');
        if ($op !== '') {
            $mb = is_string($in['size_mb'] ?? null) ? str_replace(',', '.', trim($in['size_mb'])) : '';
            if (!in_array($op, ['larger', 'smaller'], true) || !is_numeric($mb) || (float) $mb < 0.01 || (float) $mb > 1000) {
                $errors[] = 'Enter a message size between 0.01 and 1000 MB.';
            } else {
                $bytes = (int) round((float) $mb * 1048576);
                $query['size'] = $op === 'larger' ? $bytes : -$bytes;
            }
        }
        if (!$query && !$errors) {
            $errors[] = 'Add at least one condition (who it is from, a subject, text, an attachment or a size).';
        }

        $action = [];
        if (($in['act_seen'] ?? '') === '1') {
            $action['seen'] = true;
        }
        if (($in['act_flag'] ?? '') === '1') {
            $action['flag'] = true;
        }
        $to = (string) ($in['move_to'] ?? '');
        if ($to === 'spam') {
            $action['spam'] = true;
        } elseif ($to !== '') {
            if (!isset($folders[$to]) || in_array($folders[$to]->specialUse, ['\\Drafts', '\\Sent'], true)) {
                $errors[] = 'Choose a folder from the list.';
            } else {
                $action['mailbox'] = $to;
            }
        }
        $lab = [];
        foreach ((array) ($in['labels'] ?? []) as $id) {
            if (is_string($id) && isset($labels[$id])) {
                $lab[$id] = true;
            } elseif ($id !== '') {
                $errors[] = 'Choose labels from the list.';
                break;
            }
        }
        if (count($lab) > self::MAX_LABELS) {
            $errors[] = 'A rule can apply up to ' . self::MAX_LABELS . ' labels.';
        } elseif ($lab) {
            $action['labels'] = array_keys($lab);
        }
        if (!$action && !$errors) {
            $errors[] = 'Choose at least one thing to do with matching messages.';
        }

        $name = $str('name', self::MAX_NAME);
        if (mb_strlen($name) > self::MAX_NAME) {
            $errors[] = 'The rule name may have up to ' . self::MAX_NAME . ' characters.';
        }
        if ($name === '' && $query) {
            $first = $query['from'] ?? $query['to'] ?? $query['subject'] ?? $query['listId'] ?? $query['text'] ?? 'messages';
            $name = mb_substr('Rule: ' . (is_string($first) ? $first : 'messages'), 0, self::MAX_NAME);
        }
        return ['spec' => $errors ? null : ['name' => $name, 'query' => $query, 'action' => $action], 'errors' => $errors];
    }

    /** Form field values for editing an existing simple rule. @return array<string,mixed> */
    public static function toForm(Rule $r): array
    {
        $q = $r->query;
        $a = $r->action;
        $size = is_numeric($q['size'] ?? null) ? (int) $q['size'] : 0;
        return [
            'name' => $r->name, 'from' => (string) ($q['from'] ?? ''), 'to' => (string) ($q['to'] ?? ''), 'subject' => (string) ($q['subject'] ?? ''),
            'list_id' => (string) ($q['listId'] ?? ''), 'text' => (string) ($q['text'] ?? ''), 'has_attachment' => !empty($q['ha']) ? '1' : '',
            'size_op' => $size > 0 ? 'larger' : ($size < 0 ? 'smaller' : ''), 'size_mb' => $size !== 0 ? rtrim(rtrim(number_format(abs($size) / 1048576, 2, '.', ''), '0'), '.') : '',
            'act_seen' => !empty($a['seen']) ? '1' : '', 'act_flag' => !empty($a['flag']) ? '1' : '',
            'move_to' => !empty($a['spam']) ? 'spam' : (string) ($a['mailbox'] ?? ''), 'labels' => array_values(array_filter((array) ($a['labels'] ?? []), 'is_string')),
        ];
    }

    /**
     * Human-readable "If … then …" for lists. Unknown/unsupported parts are named, never hidden.
     *
     * @param array<string,Mailbox> $folders by id  @param array<string,Label> $labels by id
     * @return array{if:list<string>,then:list<string>}
     */
    public static function describe(Rule $r, array $folders, array $labels): array
    {
        $if = [];
        $q = $r->query;
        foreach (['from' => 'From contains', 'to' => 'To/Cc contains', 'subject' => 'Subject contains', 'listId' => 'List-ID contains', 'text' => 'Text contains'] as $k => $label) {
            if (isset($q[$k]) && is_scalar($q[$k])) {
                $if[] = $label . ' “' . $q[$k] . '”';
            }
        }
        if (!empty($q['ha'])) {
            $if[] = 'has an attachment';
        }
        if (is_numeric($q['size'] ?? null) && (int) $q['size'] !== 0) {
            $if[] = ((int) $q['size'] > 0 ? 'larger than ' : 'smaller than ') . human_size(abs((int) $q['size']));
        }
        $then = [];
        $a = $r->action;
        if (!empty($a['seen'])) {
            $then[] = 'mark as read';
        }
        if (!empty($a['flag'])) {
            $then[] = 'star';
        }
        if (!empty($a['spam'])) {
            $then[] = 'move to Spam';
        }
        if (is_string($a['mailbox'] ?? null)) {
            $then[] = 'move to ' . (isset($folders[$a['mailbox']]) ? $folders[$a['mailbox']]->label() : 'a folder that no longer exists');
        }
        foreach (is_array($a['labels'] ?? null) ? $a['labels'] : [] as $id) {
            $then[] = 'add label ' . (is_string($id) && isset($labels[$id]) ? '“' . $labels[$id]->name . '”' : '(deleted label)');
        }
        if (!empty($a['delete'])) {
            $then[] = 'delete permanently';
        }
        if (is_array($a['targets'] ?? null) && $a['targets']) {
            $then[] = 'forward to ' . implode(', ', array_map(static fn ($t) => is_string($t) ? $t : '?', $a['targets']));
        }
        return ['if' => $if, 'then' => $then];
    }

    /** Normalise "bob@x.com", "@x.com" or "x.com" into a from-pattern; null when it is none of those. */
    public static function blockPattern(string $raw): ?string
    {
        $v = strtolower(trim($raw));
        if ($v === '' || strlen($v) > 254 || preg_match('/[\s<>,;"\x00-\x1f]/', $v) === 1) {
            return null;
        }
        if (filter_var($v, FILTER_VALIDATE_EMAIL) !== false) {
            return $v;
        }
        $domain = ltrim($v, '@');
        return preg_match('/^(?=.{3,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain) === 1 ? '@' . $domain : null;
    }
}
