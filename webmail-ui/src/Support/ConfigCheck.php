<?php
declare(strict_types=1);

namespace Webmail\Support;

use Webmail\Core\{Crypto, Env};
use Webmail\WildDuck\{ApiException, Client};

/**
 * Go-live checklist. Returns findings; never includes secrets (token, APP_KEY) in any message.
 * Run it with: php bin/check-config.php [--php-ini=FILE] [--no-network] [--env-file=FILE] [--strict]
 */
final class ConfigCheck
{
    /**
     * @param array{php_ini?:?string,network?:bool,env_file?:?string,root?:string} $opt
     * @return list<array{level:string,text:string}> level is ok | warn | fail
     */
    public static function run(array $opt = []): array
    {
        $out = [];
        $add = static function (string $level, string $text) use (&$out): void {
            $out[] = ['level' => $level, 'text' => $text];
        };
        $prod = strtolower((string) Env::get('APP_ENV', 'production')) === 'production';

        // PHP + extensions
        PHP_VERSION_ID >= 80300 ? $add('ok', 'PHP ' . PHP_VERSION) : $add('fail', 'PHP 8.3 or newer is required (found ' . PHP_VERSION . ').');
        $missing = array_values(array_filter(['curl', 'sodium', 'mbstring', 'dom', 'libxml', 'fileinfo', 'json'], static fn (string $e): bool => !extension_loaded($e)));
        $missing ? $add('fail', 'Missing PHP extensions: ' . implode(', ', $missing)) : $add('ok', 'Required PHP extensions are loaded.');

        // Secrets / basics
        $key = (string) Env::get('APP_KEY', '');
        if ($key === '') {
            $add('fail', 'APP_KEY is not set. Generate one with: php bin/generate-key.php');
        } else {
            try {
                new Crypto($key);
                $add('ok', 'APP_KEY is valid.');
            } catch (\RuntimeException) {
                $add('fail', 'APP_KEY is not valid base64 of at least 32 random bytes. Generate one with: php bin/generate-key.php');
            }
        }
        $url = (string) Env::get('WILDDUCK_API_URL', '');
        $token = (string) Env::get('WILDDUCK_API_TOKEN', '');
        $parts = parse_url($url);
        if ($url === '' || !is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || ($parts['host'] ?? '') === '') {
            $add('fail', 'WILDDUCK_API_URL must be an http(s) URL.');
            $parts = null;
        } else {
            $add('ok', 'WildDuck API: ' . ($parts['scheme']) . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
            if ($parts['scheme'] === 'http' && !self::privateHost($parts['host'])) {
                $add('warn', 'WILDDUCK_API_URL uses plain http to a non-local host: the API token and mail travel unencrypted. Use https or a private network.');
            }
        }
        $token === '' ? $add('fail', 'WILDDUCK_API_TOKEN is not set.') : $add('ok', 'WILDDUCK_API_TOKEN is set.');
        if (!Env::bool('WILDDUCK_VERIFY_TLS', true)) {
            $add('warn', 'WILDDUCK_VERIFY_TLS is off: certificates are not verified.');
        }

        // Reachability + token (one harmless failed login attempt)
        if (($opt['network'] ?? true) && $parts !== null && $token !== '') {
            try {
                (new Client($url, $token, min(10, Env::int('WILDDUCK_TIMEOUT', 15)), Env::bool('WILDDUCK_VERIFY_TLS', true)))->json('POST', '/authenticate', [], [
                    'username' => 'config-check@invalid.example', 'password' => bin2hex(random_bytes(12)), 'scope' => 'master', 'protocol' => 'config-check',
                ]);
                $add('warn', 'WildDuck accepted a random login. Something is wrong with the target server.');
            } catch (ApiException $e) {
                if ($e->apiCode === 'AuthFailed' || $e->httpStatus === 403) {
                    $add('ok', 'WildDuck is reachable and accepted the API token.');
                } elseif ($e->isUnauthorized()) {
                    $add('fail', 'WildDuck rejected the API token (WILDDUCK_API_TOKEN).');
                } elseif ($e->isUnavailable()) {
                    $add('fail', 'WildDuck could not be reached (' . ($e->apiCode ?: 'HTTP ' . $e->httpStatus) . ').');
                } else {
                    $add('warn', 'Unexpected answer from WildDuck (HTTP ' . $e->httpStatus . ', ' . $e->apiCode . ').');
                }
            }
        }

        // Production hygiene
        if ($prod) {
            Env::bool('SESSION_COOKIE_SECURE', true) ? $add('ok', 'Session cookies are Secure.') : $add('fail', 'SESSION_COOKIE_SECURE is off in production: use HTTPS and turn it on.');
            Env::bool('APP_DEBUG', false) ? $add('warn', 'APP_DEBUG is on in production.') : $add('ok', 'APP_DEBUG is off.');
            str_starts_with((string) Env::get('APP_URL', ''), 'https://') ? $add('ok', 'APP_URL is https.') : $add('warn', 'APP_URL is not an https:// URL.');
        }
        $tp = array_values(array_filter(array_map('trim', explode(',', (string) Env::get('TRUSTED_PROXIES', ''))), static fn (string $t): bool => $t !== ''));
        foreach ($tp as $t) {
            if (in_array($t, ['*', '0.0.0.0/0', '::/0'], true)) {
                $add('warn', "TRUSTED_PROXIES contains \"$t\": every client could spoof its IP address and dodge the login rate limits.");
            }
        }
        if ($tp) {
            $add('ok', 'TRUSTED_PROXIES: ' . count($tp) . ' entr' . (count($tp) === 1 ? 'y' : 'ies') . '.');
        }

        // Storage
        $root = $opt['root'] ?? dirname(__DIR__, 2);
        $storage = rtrim((string) Env::get('STORAGE_PATH', $root . '/storage'), '/');
        foreach (['sessions', 'ratelimit'] as $d) {
            if (!is_dir("$storage/$d")) {
                @mkdir("$storage/$d", 0700, true);
            }
        }
        $probe = "$storage/sessions/.check-" . bin2hex(random_bytes(4));
        if (@file_put_contents($probe, 'x') === false) {
            $add('fail', "Storage path $storage is not writable by this user.");
        } else {
            @unlink($probe);
            $add('ok', "Storage path is writable: $storage");
        }
        $real = realpath($storage) ?: $storage;
        $pub = realpath($root . '/public') ?: $root . '/public';
        str_starts_with($real . '/', $pub . '/') ? $add('fail', 'STORAGE_PATH is inside the web root (public/): session files could be downloaded.') : $add('ok', 'Storage is outside the web root.');
        $envFile = $opt['env_file'] ?? $root . '/.env';
        if (is_file($envFile)) {
            (fileperms($envFile) & 0004) ? $add('warn', '.env is readable by every user on this machine; restrict it (chmod 640, group = the PHP-FPM group).') : $add('ok', '.env is not world-readable.');
        }

        // PHP limits (from a given ini file, otherwise the running configuration)
        $ini = static function (string $k) use ($opt): string {
            if (($opt['php_ini'] ?? null) !== null) {
                $v = @parse_ini_file($opt['php_ini'], false, INI_SCANNER_TYPED);
                return is_array($v) && array_key_exists($k, $v) ? (is_bool($v[$k]) ? ($v[$k] ? '1' : '0') : (string) $v[$k]) : (string) ini_get($k);
            }
            return (string) ini_get($k);
        };
        $mb = static fn (string $v): int => self::bytes($v);
        $maxFile = Env::int('MAX_ATTACHMENT_MB', 20) * 1048576;
        $maxMsg = Env::int('MAX_MESSAGE_MB', 25) * 1048576;
        $mb($ini('upload_max_filesize')) >= $maxFile ? $add('ok', 'upload_max_filesize ' . $ini('upload_max_filesize')) : $add('warn', 'upload_max_filesize (' . $ini('upload_max_filesize') . ') is below MAX_ATTACHMENT_MB: users will see "larger than this server allows".');
        $post = $mb($ini('post_max_size'));
        ($post === 0 || $post >= $maxMsg + 2097152) ? $add('ok', 'post_max_size ' . $ini('post_max_size')) : $add('warn', 'post_max_size (' . $ini('post_max_size') . ') should be at least MAX_MESSAGE_MB + 2 MB.');
        $mem = $mb($ini('memory_limit'));
        ($mem <= 0 || $mem >= 4 * $maxMsg) ? $add('ok', 'memory_limit ' . $ini('memory_limit')) : $add('warn', 'memory_limit (' . $ini('memory_limit') . ') is low: sending large attachments needs about 4x MAX_MESSAGE_MB.');
        if ($prod && in_array(strtolower($ini('display_errors')), ['1', 'on', 'true', 'stdout', 'stderr'], true)) {
            $add('fail', 'display_errors is on in production: PHP errors would be shown to visitors.');
        }
        if ($prod && ($opt['php_ini'] ?? null) !== null && in_array(strtolower($ini('expose_php')), ['1', 'on', 'true'], true)) {
            $add('warn', 'expose_php is on.');
        }
        if (($opt['php_ini'] ?? null) !== null) {
            foreach (array_map('trim', explode(',', $ini('disable_functions'))) as $fn) {
                if (in_array($fn, ['curl_exec', 'curl_init', 'random_bytes', 'file_put_contents'], true)) {
                    $add('fail', "disable_functions blocks $fn(), which the application needs.");
                }
            }
            if ($prod && !in_array(strtolower($ini('opcache.enable')), ['1', 'on', 'true'], true)) {
                $add('warn', 'opcache is not enabled: enable it for much better performance.');
            }
        }
        return $out;
    }

    private static function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return -1;
        }
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    }

    private static function privateHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return true;
        }
        $ip = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP);
        return $ip !== false && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
