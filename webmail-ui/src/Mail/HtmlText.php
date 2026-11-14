<?php
declare(strict_types=1);

namespace Webmail\Mail;

final class HtmlText
{
    /** Plain-text alternative for an (already sanitised) HTML body. */
    public static function toText(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="r">' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('r');
        $text = $root ? self::node($root) : '';
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    private static function node(\DOMNode $n): string
    {
        $out = '';
        foreach ($n->childNodes as $c) {
            if ($c instanceof \DOMText) {
                $out .= preg_replace('/\s+/u', ' ', $c->nodeValue ?? '');
                continue;
            }
            if (!$c instanceof \DOMElement) {
                continue;
            }
            $t = strtolower($c->tagName);
            $inner = self::node($c);
            $out .= match (true) {
                $t === 'br' => "\n",
                $t === 'li' => '- ' . trim($inner) . "\n",
                $t === 'blockquote' => "\n" . implode("\n", array_map(static fn ($l) => '> ' . $l, explode("\n", trim($inner)))) . "\n\n",
                $t === 'a' && $c->getAttribute('href') !== '' && trim($inner) !== $c->getAttribute('href') => $inner . ' (' . $c->getAttribute('href') . ')',
                in_array($t, ['p', 'div', 'tr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'pre', 'table'], true) => "\n" . trim($inner, " ") . "\n\n",
                in_array($t, ['td', 'th'], true) => $inner . ' ',
                default => $inner,
            };
        }
        return $out;
    }
}
