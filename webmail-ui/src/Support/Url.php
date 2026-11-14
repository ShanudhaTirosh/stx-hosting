<?php
declare(strict_types=1);

namespace Webmail\Support;

final class Url
{
    /** Accept only same-site relative paths to the app's own pages (blocks open redirects). */
    public static function safeReturn(?string $path, string $fallback): string
    {
        if ($path === null || $path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\')
            || preg_match('/[\x00-\x1f\x7f]/', $path) === 1) {
            return $fallback;
        }
        $p = parse_url($path, PHP_URL_PATH) ?: '';
        foreach (['/search', '/mail', '/folders', '/labels'] as $exact) {
            if ($p === $exact) {
                return $path;
            }
        }
        foreach (['/mail/', '/label/', '/thread/'] as $prefix) {
            if (str_starts_with($p, $prefix)) {
                return $path;
            }
        }
        return $fallback;
    }

    public static function cursor(?string $v): ?string
    {
        return $v !== null && preg_match('#^[A-Za-z0-9_\-+/=.]{1,512}$#', $v) === 1 ? $v : null;
    }
}
