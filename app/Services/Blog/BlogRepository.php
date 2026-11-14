<?php

namespace App\Services\Blog;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BlogRepository
{
    public static ?string $basePath = null;

    /**
     * Get all published blog articles sorted by date descending.
     *
     * @return Collection<int, array{
     *     slug: string,
     *     title: string,
     *     summary: string,
     *     author: string,
     *     date: string,
     *     read_time: string,
     *     keywords: string,
     *     category: string,
     *     body: string
     * }>
     */
    public function articles(): Collection
    {
        $dir = self::$basePath ?? base_path('docs/blog');

        if (! is_dir($dir)) {
            return collect();
        }

        return collect(glob($dir.'/*.md') ?: [])
            ->map(fn (string $file) => $this->parse($file))
            ->filter()
            ->sortByDesc('date')
            ->values();
    }

    public function find(string $slug): ?array
    {
        if (! preg_match('/^[a-z0-9-]+$/', $slug)) {
            return null;
        }

        return $this->articles()->firstWhere('slug', $slug);
    }

    /**
     * Render safe HTML from the article markdown body.
     */
    public function html(array $article): string
    {
        return (string) Str::markdown($article['body'], [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    private function parse(string $path): ?array
    {
        $contents = @file_get_contents($path);
        if ($contents === false || $contents === '') {
            return null;
        }

        $slug = basename($path, '.md');
        $meta = [
            'title' => Str::title(str_replace('-', ' ', $slug)),
            'summary' => '',
            'author' => 'STX Cloud Engineering',
            'date' => date('Y-m-d', filemtime($path) ?: time()),
            'read_time' => '5 min read',
            'keywords' => 'cloud hosting, vps, sri lanka',
            'category' => 'Engineering',
        ];
        $body = $contents;

        if (preg_match('/^---\s*\r?\n(.*?)\r?\n---\s*\r?\n(.*)$/s', $contents, $matches)) {
            foreach (preg_split('/\r?\n/', $matches[1]) as $line) {
                if (! str_contains($line, ':')) {
                    continue;
                }
                [$key, $value] = explode(':', $line, 2);
                $k = trim($key);
                $v = trim($value, " \t\n\r\0\x0B\"'");
                if ($k !== '' && array_key_exists($k, $meta)) {
                    $meta[$k] = $v;
                }
            }
            $body = trim($matches[2]);
        }

        return array_merge($meta, [
            'slug' => $slug,
            'body' => $body,
        ]);
    }
}
