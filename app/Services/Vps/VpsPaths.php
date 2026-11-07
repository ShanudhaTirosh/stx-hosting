<?php

namespace App\Services\Vps;

/** Path handling for the file manager: always absolute, no `.`/`..` tricks, no control characters. */
class VpsPaths
{
    public static function normalize(string $path): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
            throw new \RuntimeException('That path is not valid.');
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);

                continue;
            }
            $parts[] = $part;
        }

        return '/'.implode('/', $parts);
    }

    public static function join(string $directory, string $name): string
    {
        if ($name === '' || str_contains($name, '/') || preg_match('/[\x00-\x1F\x7F]/', $name) || in_array($name, ['.', '..'], true)) {
            throw new \RuntimeException('That file name is not valid.');
        }

        return self::normalize(rtrim($directory, '/').'/'.$name);
    }

    public static function parent(string $path): string
    {
        return self::normalize(dirname(self::normalize($path)));
    }

    /** Make an uploaded file's name safe to use on the server. */
    public static function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F\/]/', '', $name) ?? '';

        return in_array($name, ['', '.', '..'], true) ? 'upload' : $name;
    }
}
