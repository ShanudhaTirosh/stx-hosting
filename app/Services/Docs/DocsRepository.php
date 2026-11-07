<?php

namespace App\Services\Docs;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The help center. Articles are Markdown files in docs/kb/<audience>/, each starting with a small header:
 *
 *   ---
 *   title: How to pay
 *   summary: One line shown in lists and search.
 *   order: 2
 *   ---
 *
 * 'client' articles are public; 'admin' articles are for staff. Only files inside those two folders are
 * ever read, and a slug can only be letters, digits and dashes.
 */
class DocsRepository
{
    public const AUDIENCES = ['client', 'admin'];

    public static ?string $basePath = null;

    /** @return Collection<int, array{slug: string, title: string, summary: string, order: int, body: string}> */
    public function articles(string $audience): Collection
    {
        abort_unless(in_array($audience, self::AUDIENCES, true), 404);

        $dir = (self::$basePath ?? base_path('docs/kb')).'/'.$audience;

        return collect(glob($dir.'/*.md') ?: [])
            ->map(fn (string $file) => $this->parse($file))
            ->filter()
            ->sortBy([['order', 'asc'], ['title', 'asc']])
            ->values();
    }

    public function find(string $audience, string $slug): ?array
    {
        if (! preg_match('/^[a-z0-9-]+$/', $slug)) {
            return null;
        }

        return $this->articles($audience)->firstWhere('slug', $slug);
    }

    /** Safe HTML for an article body: no raw HTML from the file, no script links. */
    public function html(array $article): string
    {
        return (string) Str::markdown($article['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    /**
     * Rank articles by how well they match every word: title counts most, then the summary, then the text.
     *
     * @return Collection<int, array{article: array, snippet: string, score: int}>
     */
    public function search(string $audience, string $query): Collection
    {
        $words = collect(preg_split('/\s+/', Str::lower(trim($query)), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($w) => mb_strlen($w) >= 2)->unique()->take(6)->values();

        if ($words->isEmpty()) {
            return collect();
        }

        return $this->articles($audience)->map(function (array $a) use ($words) {
            $title = Str::lower($a['title']);
            $summary = Str::lower($a['summary']);
            $body = Str::lower($a['body']);
            $score = 0;

            foreach ($words as $w) {
                $inTitle = str_contains($title, $w);
                $inSummary = str_contains($summary, $w);
                $inBody = substr_count($body, $w);

                if (! $inTitle && ! $inSummary && $inBody === 0) {
                    return null; // every word has to appear somewhere
                }

                $score += ($inTitle ? 10 : 0) + ($inSummary ? 4 : 0) + min(5, $inBody);
            }

            return ['article' => $a, 'snippet' => $this->snippet($a['body'], (string) $words->first()), 'score' => $score];
        })->filter()->sortByDesc('score')->take(20)->values();
    }

    private function snippet(string $body, string $word): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) Str::markdown($body, ['html_input' => 'strip']))));
        $pos = mb_stripos($plain, $word);
        $start = max(0, ($pos === false ? 0 : $pos) - 60);

        return ($start > 0 ? '...' : '').trim(mb_substr($plain, $start, 200)).(mb_strlen($plain) > $start + 200 ? '...' : '');
    }

    private function parse(string $file): ?array
    {
        $raw = (string) file_get_contents($file);

        if (! preg_match('/\A---\R(.*?)\R---\R(.*)\z/s', $raw, $m)) {
            return null; // a file without the header is not an article
        }

        $meta = [];
        foreach (preg_split('/\R/', $m[1]) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $meta[trim($k)] = trim($v);
            }
        }

        if (blank($meta['title'] ?? null)) {
            return null;
        }

        return [
            'slug' => basename($file, '.md'),
            'title' => $meta['title'],
            'summary' => $meta['summary'] ?? '',
            'order' => (int) ($meta['order'] ?? 100),
            'body' => ltrim($m[2]),
        ];
    }
}
