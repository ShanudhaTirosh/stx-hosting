<?php
declare(strict_types=1);

namespace Webmail\Support;

/**
 * Allow-list sanitizer for untrusted email HTML.
 * Defence in depth: the result is ALSO served from a sandboxed, CSP-locked endpoint,
 * so a sanitizer gap cannot execute script in the app origin.
 */
final class HtmlSanitizer
{
    private const TAGS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'dd', 'del', 'div', 'dl', 'dt', 'em',
        'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins', 'li', 'ol', 'p', 'pre', 'q', 's', 'small', 'span', 'strike',
        'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'tt', 'u', 'ul',
    ];
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'applet', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math', 'video', 'audio', 'canvas', 'link', 'meta', 'base', 'template', 'noscript', 'frame', 'frameset', 'head', 'title'];
    private const ATTRS = ['href', 'src', 'alt', 'title', 'width', 'height', 'align', 'valign', 'bgcolor', 'color', 'colspan', 'rowspan', 'border', 'cellpadding', 'cellspacing', 'style', 'dir', 'lang', 'face', 'size', 'target', 'rel', 'data-signature'];
    private const CSS_PROPS = ['color', 'background-color', 'font-family', 'font-size', 'font-weight', 'font-style', 'text-align', 'text-decoration', 'line-height', 'margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right', 'padding', 'padding-top', 'padding-bottom', 'padding-left', 'padding-right', 'border', 'border-top', 'border-bottom', 'border-left', 'border-right', 'border-collapse', 'border-color', 'border-width', 'border-style', 'width', 'max-width', 'height', 'vertical-align', 'white-space', 'display'];

    /** @return array{html:string,blockedImages:int} */
    public static function clean(string $html, bool $allowRemoteImages = false): array
    {
        if (trim($html) === '') {
            return ['html' => '', 'blockedImages' => 0];
        }
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__wm">' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('__wm');
        if ($root === null) {
            return ['html' => '', 'blockedImages' => 0];
        }
        $blocked = 0;
        self::walk($root, $allowRemoteImages, $blocked);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }
        return ['html' => $out, 'blockedImages' => $blocked];
    }

    /**
     * Sanitise HTML for an OUTGOING message: same allow-list, and no images except embedded data: images
     * (relative/proxy image paths would be broken for recipients, remote images leak the sender's reading habits).
     */
    public static function cleanOutgoing(string $html): string
    {
        $clean = self::clean($html, false)['html'];
        if ($clean === '') {
            return '';
        }
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__wm">' . $clean . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('__wm');
        if ($root === null) {
            return '';
        }
        foreach (iterator_to_array($doc->getElementsByTagName('img')) as $img) {
            if (!str_starts_with(strtolower(trim($img->getAttribute('src'))), 'data:image/')) {
                $img->parentNode?->removeChild($img);
            }
        }
        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    private static function walk(\DOMNode $node, bool $remote, int &$blocked): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction || $child instanceof \DOMCdataSection) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }
            if (!in_array($tag, self::TAGS, true)) {
                // Unknown tag: keep its (sanitised) children, drop the wrapper.
                self::walk($child, $remote, $blocked);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            self::attributes($child, $tag, $remote, $blocked);
            self::walk($child, $remote, $blocked);
        }
    }

    private static function attributes(\DOMElement $el, string $tag, bool $remote, int &$blocked): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            if (!in_array($name, self::ATTRS, true) || str_starts_with($name, 'on')) {
                $el->removeAttribute($attr->name);
            }
        }
        if ($el->hasAttribute('style')) {
            $css = self::css($el->getAttribute('style'));
            $css === '' ? $el->removeAttribute('style') : $el->setAttribute('style', $css);
        }
        if ($tag === 'a') {
            $href = $el->getAttribute('href');
            if ($href !== '' && !self::safeUrl($href, ['http', 'https', 'mailto', 'tel'])) {
                $el->removeAttribute('href');
            }
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener noreferrer nofollow');
        } else {
            $el->removeAttribute('href');
            $el->removeAttribute('target');
        }
        if ($tag === 'img') {
            $src = trim($el->getAttribute('src'));
            if (preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[a-z0-9+/=\s]+$#i', $src) === 1) {
                return; // inline image
            }
            if (preg_match('#^/mail/[a-f0-9]{24}/[1-9][0-9]{0,9}/attachment/ATT[0-9]{1,6}(\?inline=1)?$#', $src) === 1) {
                return; // inline (cid) image served by our own attachment proxy
            }
            if (!$remote) {
                $el->removeAttribute('src');
                $el->setAttribute('alt', $el->getAttribute('alt') !== '' ? $el->getAttribute('alt') : 'Image blocked');
                $el->setAttribute('data-blocked', '1');
                $blocked++;
            } elseif (!self::safeUrl($src, ['https'])) {
                $el->removeAttribute('src');
            } else {
                $el->setAttribute('referrerpolicy', 'no-referrer');
            }
        } else {
            $el->removeAttribute('src');
        }
    }

    private static function safeUrl(string $url, array $schemes): bool
    {
        $u = preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';
        if ($u === '' || str_starts_with($u, '#')) {
            return $u !== '';
        }
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $u, $m) !== 1) {
            return false; // relative URLs are meaningless in a sandboxed email frame
        }
        return in_array(strtolower($m[1]), $schemes, true);
    }

    private static function css(string $style): string
    {
        $keep = [];
        foreach (explode(';', $style) as $decl) {
            if (!str_contains($decl, ':')) {
                continue;
            }
            [$prop, $val] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            $v = strtolower($val);
            if (!in_array($prop, self::CSS_PROPS, true) || $val === ''
                || preg_match('/url\s*\(|expression|javascript:|@import|behavior|binding|\\\\|[<>{}]/i', $v) === 1) {
                continue;
            }
            $keep[] = $prop . ':' . $val;
        }
        return implode(';', $keep);
    }
}
