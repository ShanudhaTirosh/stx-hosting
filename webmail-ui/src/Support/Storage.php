<?php
declare(strict_types=1);

namespace Webmail\Support;

/** Housekeeping for the file-backed session and rate-limit stores (PHP's own session GC does not cover a custom save path). */
final class Storage
{
    /**
     * Delete regular files in $dir whose last modification is older than $maxAgeSec. Dot-files are kept.
     *
     * @return int number of files removed
     */
    public static function prune(string $dir, int $maxAgeSec, ?int $now = null): int
    {
        $now ??= time();
        $removed = 0;
        $it = @scandir($dir);
        foreach ($it === false ? [] : $it as $entry) {
            if ($entry === '' || $entry[0] === '.') {
                continue;
            }
            $f = $dir . '/' . $entry;
            if (is_file($f) && !is_link($f) && ($m = @filemtime($f)) !== false && $now - $m > $maxAgeSec && @unlink($f)) {
                $removed++;
            }
        }
        return $removed;
    }
}
