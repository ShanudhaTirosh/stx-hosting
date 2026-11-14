<?php
declare(strict_types=1);

namespace Webmail\Core;

/** Small file-backed fixed-window limiter (no extra services). Swap for Redis if you scale out. */
final class RateLimiter
{
    public function __construct(private readonly string $dir)
    {
    }

    public function tooMany(string $key, int $max, int $windowSec): bool
    {
        return $this->read($key, $windowSec)['n'] >= $max;
    }

    public function hit(string $key, int $windowSec): void
    {
        $f = $this->file($key);
        $fp = fopen($f, 'c+');
        if ($fp === false) {
            return;
        }
        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $d = json_decode($raw ?: '', true);
        $now = time();
        if (!is_array($d) || ($d['t'] ?? 0) + $windowSec < $now) {
            $d = ['t' => $now, 'n' => 0];
        }
        $d['n']++;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($d));
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    public function clear(string $key): void
    {
        @unlink($this->file($key));
    }

    public function retryAfter(string $key, int $windowSec): int
    {
        $d = $this->read($key, $windowSec);
        return max(1, ($d['t'] + $windowSec) - time());
    }

    /** @return array{t:int,n:int} */
    private function read(string $key, int $windowSec): array
    {
        $raw = @file_get_contents($this->file($key));
        $d = json_decode($raw ?: '', true);
        if (!is_array($d) || ($d['t'] ?? 0) + $windowSec < time()) {
            return ['t' => time(), 'n' => 0];
        }
        return ['t' => (int) $d['t'], 'n' => (int) $d['n']];
    }

    private function file(string $key): string
    {
        return $this->dir . '/' . hash('sha256', $key) . '.json';
    }
}
