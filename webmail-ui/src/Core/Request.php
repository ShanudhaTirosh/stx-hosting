<?php
declare(strict_types=1);

namespace Webmail\Core;

final class Request
{
    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,string> $headers
     * @param array<string,mixed> $files
     * @param array<string,string> $params
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $headers,
        public readonly string $ip,
        public readonly array $files = [],
        public array $params = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = (string) $v;
            }
        }
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        // Method override is accepted from POST forms only, and only for PUT/PATCH/DELETE.
        if ($method === 'POST' && isset($_POST['_method']) && in_array(strtoupper((string) $_POST['_method']), ['PUT', 'PATCH', 'DELETE'], true)) {
            $method = strtoupper((string) $_POST['_method']);
        }

        return new self($method, rtrim($path, '/') ?: '/', $_GET, $_POST, $headers, self::clientIp($_SERVER, (string) Env::get('TRUSTED_PROXIES', '')), $_FILES);
    }

    /**
     * The real client address. X-Forwarded-For is honoured ONLY when the connection comes from a configured trusted proxy
     * (TRUSTED_PROXIES: comma-separated IPs or CIDRs); then the right-most address that is not itself a trusted proxy wins,
     * so a client cannot choose its own address by sending the header.
     *
     * @param array<string,mixed> $server
     */
    public static function clientIp(array $server, string $trusted): string
    {
        $remote = is_string($server['REMOTE_ADDR'] ?? null) && filter_var($server['REMOTE_ADDR'], FILTER_VALIDATE_IP) !== false ? $server['REMOTE_ADDR'] : '0.0.0.0';
        $list = array_values(array_filter(array_map('trim', explode(',', $trusted)), static fn (string $t): bool => $t !== ''));
        $xff = is_string($server['HTTP_X_FORWARDED_FOR'] ?? null) ? $server['HTTP_X_FORWARDED_FOR'] : '';
        if (!$list || $xff === '' || !self::inList($remote, $list)) {
            return $remote;
        }
        foreach (array_reverse(array_map('trim', explode(',', $xff))) as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                return $remote; // malformed header: do not guess
            }
            if (!self::inList($ip, $list)) {
                return $ip;
            }
        }
        return $remote;
    }

    /** @param list<string> $list IPs or CIDRs */
    private static function inList(string $ip, array $list): bool
    {
        $bin = @inet_pton($ip);
        foreach ($list as $entry) {
            [$net, $bits] = array_pad(explode('/', $entry, 2), 2, null);
            $nb = @inet_pton((string) $net);
            if ($bin === false || $nb === false || strlen($nb) !== strlen($bin)) {
                continue;
            }
            $max = strlen($nb) * 8;
            $bits = $bits === null ? $max : (ctype_digit($bits) ? min((int) $bits, $max) : -1);
            if ($bits < 0) {
                continue;
            }
            $bytes = intdiv($bits, 8);
            if (substr($bin, 0, $bytes) !== substr($nb, 0, $bytes)) {
                continue;
            }
            $rem = $bits % 8;
            if ($rem === 0 || ((ord($bin[$bytes]) ^ ord($nb[$bytes])) & (0xFF << (8 - $rem)) & 0xFF) === 0) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,string> $params */
    public function withParams(array $params): self
    {
        $c = clone $this;
        $c->params = $params;
        return $c;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? (string) $v : $default;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** Path plus query string, suitable for a same-site "return" link. */
    public function uri(): string
    {
        $q = http_build_query($this->query);
        return $this->path . ($q !== '' ? '?' . $q : '');
    }
}
