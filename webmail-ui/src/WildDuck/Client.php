<?php
declare(strict_types=1);

namespace Webmail\WildDuck;

/**
 * Thin, dependency-free HTTP client for the WildDuck REST API.
 * - Token is sent in X-Access-Token (never in the URL).
 * - GETs are retried once on connection errors and 502/503/504.
 * - JSON responses must decode and carry success=true, otherwise ApiException.
 */
class Client
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeout = 15,
        private readonly bool $verifyTls = true,
    ) {
    }

    public function withToken(string $token): static
    {
        return new static($this->baseUrl, $token, $this->timeout, $this->verifyTls);
    }

    public function withTimeout(int $seconds): static
    {
        return new static($this->baseUrl, $this->token, max(1, $seconds), $this->verifyTls);
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    public function json(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $attempts = $method === 'GET' ? 2 : 1;
        $last = null;
        for ($i = 0; $i < $attempts; $i++) {
            try {
                [$status, , $raw] = $this->exec($method, $path, $query, $body, null);
                return $this->decode($status, $raw);
            } catch (ApiException $e) {
                $last = $e;
                if (!($e->isUnavailable() && $e->httpStatus !== 500)) {
                    throw $e;
                }
            }
        }
        throw $last;
    }

    /**
     * Binary download (attachments, raw message). Enforces a size cap.
     *
     * @param array<string,mixed> $query
     * @return array{status:int,contentType:string,disposition:string,body:string}
     */
    public function download(string $path, array $query = [], int $maxBytes = 26214400): array
    {
        [$status, $headers, $raw] = $this->exec('GET', $path, $query, null, $maxBytes);
        if ($status >= 400) {
            $this->decode($status, $raw); // throws
        }
        return [
            'status' => $status,
            'contentType' => $headers['content-type'] ?? 'application/octet-stream',
            'disposition' => $headers['content-disposition'] ?? '',
            'body' => $raw,
        ];
    }

    /** @param array<string,mixed> $query @param array<string,mixed>|null $body
     *  @return array{int,array<string,string>,string} */
    private function exec(string $method, string $path, array $query, ?array $body, ?int $maxBytes): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        if ($query) {
            $url .= '?' . http_build_query(array_map(static fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : $v, $query), '', '&', PHP_QUERY_RFC3986);
        }
        $ch = curl_init($url);
        $headers = [];
        $received = 0;
        $buf = '';
        $tooBig = false;
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => array_values(array_filter([
                'Accept: application/json, */*',
                $body !== null ? 'Content-Type: application/json' : null,
                $this->token !== '' ? 'X-Access-Token: ' . $this->token : null,
            ])),
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
                $p = strpos($line, ':');
                if ($p !== false) {
                    $headers[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$buf, &$received, &$tooBig, $maxBytes): int {
                $received += strlen($chunk);
                if ($maxBytes !== null && $received > $maxBytes) {
                    $tooBig = true;
                    return 0; // abort transfer
                }
                $buf .= $chunk;
                return strlen($chunk);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_errno($ch);
        curl_close($ch);

        if ($tooBig) {
            throw new ApiException('The file is too large to download here.', 413, 'TooLarge');
        }
        if ($ok === false || $err !== 0) {
            throw new ApiException('Mail server unreachable.', 0, 'Unreachable');
        }
        return [$status, $headers, $buf];
    }

    /** @return array<string,mixed> */
    private function decode(int $status, string $raw): array
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new ApiException('Unexpected response from mail server.', $status >= 400 ? $status : 502, 'BadResponse');
        }
        if ($status >= 400 || isset($data['error'])) {
            $msg = is_string($data['error'] ?? null) ? $data['error'] : '';
            $code = is_string($data['code'] ?? null) ? $data['code'] : '';
            throw new ApiException($status >= 500 ? 'Mail server error.' : $msg, $status ?: 502, $code);
        }
        if (($data['success'] ?? null) !== true) {
            throw new ApiException('Unexpected response from mail server.', 502, 'BadResponse');
        }
        return $data;
    }
}
