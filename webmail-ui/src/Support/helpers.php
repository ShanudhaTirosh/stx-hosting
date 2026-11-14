<?php
declare(strict_types=1);

/** Escape for HTML text and attribute contexts. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Inline outline icon (Lucide, 1.5px stroke) at the given pixel size. */
function icon(string $name, int $size = 16): string
{
    static $icons = null;
    $icons ??= json_decode((string) file_get_contents(__DIR__ . '/icons.json'), true) ?: [];
    $inner = $icons[$name] ?? '';
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $inner . '</svg>';
}

/** Compact relative/absolute date for lists. */
function short_date(?\DateTimeImmutable $d, ?\DateTimeImmutable $now = null): string
{
    if ($d === null) {
        return '';
    }
    $now ??= new \DateTimeImmutable();
    if ($d->format('Y-m-d') === $now->format('Y-m-d')) {
        return $d->format('H:i');
    }
    if ($d->format('Y') === $now->format('Y')) {
        return $d->format('M j');
    }
    return $d->format('M j, Y');
}

function human_size(int $bytes): string
{
    foreach (['B', 'KB', 'MB', 'GB'] as $i => $u) {
        if ($bytes < 1024 ** ($i + 1) || $u === 'GB') {
            return $i === 0 ? $bytes . ' B' : number_format($bytes / 1024 ** $i, 1) . ' ' . $u;
        }
    }
    return $bytes . ' B';
}
