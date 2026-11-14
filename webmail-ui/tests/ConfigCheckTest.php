<?php
declare(strict_types=1);

use Webmail\Core\Env;
use Webmail\Support\ConfigCheck;

/** Run the checker with a temporary environment, then restore it. @param array<string,string> $env */
function checkWith(array $env, array $opt = []): array
{
    $keys = ['APP_ENV', 'APP_KEY', 'APP_URL', 'APP_DEBUG', 'WILDDUCK_API_URL', 'WILDDUCK_API_TOKEN', 'WILDDUCK_VERIFY_TLS', 'SESSION_COOKIE_SECURE', 'TRUSTED_PROXIES', 'STORAGE_PATH', 'MAX_ATTACHMENT_MB', 'MAX_MESSAGE_MB', 'WILDDUCK_TIMEOUT'];
    $old = []; foreach ($keys as $k) { $old[$k] = Env::get($k) ?? ''; Env::set($k, ''); }
    $tmp = sys_get_temp_dir() . '/cc-' . bin2hex(random_bytes(3));
    $base = ['APP_ENV' => 'production', 'APP_KEY' => base64_encode(random_bytes(32)), 'APP_URL' => 'https://mail.example.org', 'WILDDUCK_API_URL' => Servers::WD, 'WILDDUCK_API_TOKEN' => 'svc-token', 'STORAGE_PATH' => $tmp, 'WILDDUCK_TIMEOUT' => '3'];
    foreach ($env + $base as $k => $v) { Env::set($k, $v); }
    try { $r = ConfigCheck::run($opt + ['root' => dirname(__DIR__)]); } finally { foreach ($old as $k => $v) { Env::set($k, $v); } exec('rm -rf ' . escapeshellarg($tmp)); }
    return $r;
}
function levels(array $r, string $level): string { return implode(' | ', array_column(array_filter($r, fn ($f) => $f['level'] === $level), 'text')); }

test('config: a good setup passes and says what it verified', function () {
    $r = checkWith([]); eq(levels($r, 'fail'), '', 'no failures'); has(levels($r, 'ok'), 'reachable and accepted the API token'); has(levels($r, 'ok'), 'APP_KEY is valid'); has(levels($r, 'ok'), 'Storage path is writable'); has(levels($r, 'ok'), 'Storage is outside the web root');
});
test('config: bad token, unreachable server, bad key and missing settings all fail loudly', function () {
    has(levels(checkWith(['WILDDUCK_API_TOKEN' => 'wrong-token']), 'fail'), 'rejected the API token');
    has(levels(checkWith(['WILDDUCK_API_URL' => 'http://127.0.0.1:18999']), 'fail'), 'could not be reached');
    has(levels(checkWith(['APP_KEY' => 'short']), 'fail'), 'APP_KEY is not valid'); has(levels(checkWith(['APP_KEY' => '']), 'fail'), 'APP_KEY is not set');
    has(levels(checkWith(['WILDDUCK_API_URL' => 'ftp://x']), 'fail'), 'must be an http(s) URL'); has(levels(checkWith(['WILDDUCK_API_URL' => '']), 'fail'), 'must be an http(s) URL');
    has(levels(checkWith(['WILDDUCK_API_TOKEN' => '']), 'fail'), 'WILDDUCK_API_TOKEN is not set');
    has(levels(checkWith(['SESSION_COOKIE_SECURE' => 'false']), 'fail'), 'SESSION_COOKIE_SECURE is off');
});
test('config: risky-but-working setups produce warnings', function () {
    has(levels(checkWith(['WILDDUCK_API_URL' => 'http://mail.example.org:8080'], ['network' => false]), 'warn'), 'plain http to a non-local host');
    eq(strpos(levels(checkWith(['WILDDUCK_API_URL' => 'http://10.1.2.3:8080'], ['network' => false]), 'warn'), 'plain http'), false, 'private networks are fine');
    eq(strpos(levels(checkWith(['WILDDUCK_API_URL' => 'http://localhost:8080'], ['network' => false]), 'warn'), 'plain http'), false);
    has(levels(checkWith(['WILDDUCK_VERIFY_TLS' => 'false']), 'warn'), 'certificates are not verified');
    has(levels(checkWith(['TRUSTED_PROXIES' => '0.0.0.0/0']), 'warn'), 'spoof its IP'); has(levels(checkWith(['TRUSTED_PROXIES' => '10.0.0.0/8, 127.0.0.1']), 'ok'), '2 entries');
    has(levels(checkWith(['APP_URL' => 'http://mail.example.org']), 'warn'), 'not an https'); has(levels(checkWith(['APP_DEBUG' => 'true']), 'warn'), 'APP_DEBUG is on');
    eq(levels(checkWith(['APP_ENV' => 'local', 'SESSION_COOKIE_SECURE' => 'false']), 'fail'), '', 'development is allowed insecure cookies');
});
test('config: PHP limits are checked against the configured attachment sizes', function () {
    $ini = tempnam(sys_get_temp_dir(), 'ini'); file_put_contents($ini, "upload_max_filesize = 2M\npost_max_size = 8M\nmemory_limit = 64M\ndisplay_errors = On\nexpose_php = On\nopcache.enable = 0\ndisable_functions = \"curl_exec,system\"\n");
    $w = levels(checkWith([], ['php_ini' => $ini]), 'warn'); $f = levels(checkWith([], ['php_ini' => $ini]), 'fail');
    has($w, 'upload_max_filesize (2M)'); has($w, 'post_max_size (8M)'); has($w, 'memory_limit (64M)'); has($w, 'expose_php'); has($w, 'opcache is not enabled'); has($f, 'display_errors is on'); has($f, 'blocks curl_exec');
    file_put_contents($ini, "upload_max_filesize = 25M\npost_max_size = 30M\nmemory_limit = 256M\ndisplay_errors = Off\nexpose_php = Off\nopcache.enable = 1\n");
    $r = checkWith([], ['php_ini' => $ini]); eq(levels($r, 'fail'), ''); eq(levels($r, 'warn'), ''); has(levels($r, 'ok'), 'upload_max_filesize 25M');
    $r = checkWith(['MAX_ATTACHMENT_MB' => '50'], ['php_ini' => $ini]); has(levels($r, 'warn'), 'upload_max_filesize (25M)');
    @unlink($ini);
});
test('config: storage inside the web root and world-readable .env are flagged', function () {
    $r = checkWith(['STORAGE_PATH' => dirname(__DIR__) . '/public/.storage-test']); has(levels($r, 'fail'), 'inside the web root'); exec('rm -rf ' . escapeshellarg(dirname(__DIR__) . '/public/.storage-test'));
    $f = tempnam(sys_get_temp_dir(), 'env'); chmod($f, 0644); has(levels(checkWith([], ['env_file' => $f]), 'warn'), '.env is readable by every user'); chmod($f, 0640); has(levels(checkWith([], ['env_file' => $f]), 'ok'), '.env is not world-readable'); @unlink($f);
});
test('config: the report never contains the token or the app key', function () {
    $key = base64_encode(random_bytes(32)); $r = checkWith(['WILDDUCK_API_TOKEN' => 'super-secret-token-value', 'APP_KEY' => $key, 'WILDDUCK_API_URL' => 'http://127.0.0.1:18999']);
    $all = json_encode($r); lacks($all, 'super-secret-token-value'); lacks($all, $key);
    $r = checkWith(['WILDDUCK_API_URL' => 'http://user:pa55word@127.0.0.1:18080']); lacks(json_encode($r), 'pa55word');
});
test('config: the CLI exits non-zero on failures, zero otherwise, and prints no secrets', function () {
    $run = function (array $env, array $args = []): array {
        $cmd = array_merge([PHP_BINARY, dirname(__DIR__) . '/bin/check-config.php', '--env-file=/nonexistent'], $args);
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env + ['PATH' => getenv('PATH')]);
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); $code = proc_close($p); return [$code, $out];
    };
    $good = ['APP_ENV' => 'production', 'APP_KEY' => base64_encode(random_bytes(32)), 'APP_URL' => 'https://m.example.org', 'WILDDUCK_API_URL' => Servers::WD, 'WILDDUCK_API_TOKEN' => 'svc-token', 'STORAGE_PATH' => sys_get_temp_dir() . '/cli-' . bin2hex(random_bytes(3)), 'WILDDUCK_TIMEOUT' => '3'];
    [$c, $o] = $run($good); eq($c, 0, $o); has($o, '[ OK ] WildDuck is reachable'); has($o, 'failures'); lacks($o, 'svc-token');
    [$c, $o] = $run(['WILDDUCK_API_TOKEN' => 'nope'] + $good); eq($c, 1); has($o, '[FAIL]'); lacks($o, 'nope');
    [$c, $o] = $run(['APP_KEY' => ''] + $good); eq($c, 1); has($o, 'generate-key');
    [$c, $o] = $run(['WILDDUCK_API_URL' => 'http://mail.example.org'] + $good, ['--no-network']); eq($c, 0, 'warnings alone pass'); [$c] = $run(['WILDDUCK_API_URL' => 'http://mail.example.org'] + $good, ['--no-network', '--strict']); eq($c, 1, '--strict fails on warnings');
    exec('rm -rf ' . escapeshellarg($good['STORAGE_PATH']));
    $k = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/bin/generate-key.php'))); eq(strlen((string) base64_decode($k, true)), 32);
});
