<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

/** @var array<string,callable> $GLOBALS['__tests'] */
$GLOBALS['__tests'] = [];
$GLOBALS['__fail'] = 0;
$GLOBALS['__pass'] = 0;

final class SkipTest extends RuntimeException {}
function skip(string $why): never { throw new SkipTest($why); }

function test(string $name, callable $fn): void { $GLOBALS['__tests'][$name] = $fn; }

function check(bool $cond, string $msg = "assertion failed"): void
{
    if (!$cond) { throw new AssertionError($msg); }
}
function eq(mixed $actual, mixed $expected, string $msg = ''): void
{
    if ($actual !== $expected) {
        throw new AssertionError(($msg !== '' ? "$msg: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function has(string $haystack, string $needle, string $msg = ''): void
{
    if (!str_contains($haystack, $needle)) { throw new AssertionError(($msg !== '' ? "$msg: " : '') . "missing '" . $needle . "'"); }
}
function lacks(string $haystack, string $needle, string $msg = ''): void
{
    if (str_contains($haystack, $needle)) { throw new AssertionError(($msg !== '' ? "$msg: " : '') . "unexpected '" . $needle . "'"); }
}

/** Starts `php -S` servers for the duration of the run. */
final class Servers
{
    /** @var list<resource> */
    private static array $procs = [];
    public const WD = 'http://127.0.0.1:18080';
    public const APP = 'http://127.0.0.1:18081';
    public const APP_DOWN = 'http://127.0.0.1:18082'; // points at a dead WildDuck
    public static string $tmp = '';

    public static function start(): void
    {
        self::$tmp = sys_get_temp_dir() . '/wm-tests-' . bin2hex(random_bytes(4));
        mkdir(self::$tmp . '/a', 0700, true);
        mkdir(self::$tmp . '/b', 0700, true);
        $root = dirname(__DIR__);
        $base = [
            'APP_KEY' => base64_encode(str_repeat('k', 32)), 'SESSION_COOKIE_SECURE' => 'false', 'WILDDUCK_API_TOKEN' => 'svc-token',
            'WILDDUCK_TIMEOUT' => '3', 'PATH' => getenv('PATH') ?: '/usr/bin',
        ];
        self::spawn('127.0.0.1:18080', $root . '/tests/Support/fake_wildduck.php', ['FAKE_WD_STATE' => self::$tmp . '/wd.json', 'PATH' => $base['PATH']], null);
        self::spawn('127.0.0.1:18081', $root . '/public/index.php', $base + ['WILDDUCK_API_URL' => self::WD, 'STORAGE_PATH' => self::$tmp . '/a', 'PHP_CLI_SERVER_WORKERS' => '4'], $root . '/public');
        self::spawn('127.0.0.1:18082', $root . '/public/index.php', $base + ['WILDDUCK_API_URL' => 'http://127.0.0.1:18999', 'STORAGE_PATH' => self::$tmp . '/b'], $root . '/public');
        foreach ([18080, 18081, 18082] as $port) {
            $ok = false;
            for ($i = 0; $i < 50 && !$ok; $i++) {
                $s = @fsockopen('127.0.0.1', $port, $e, $es, 0.2);
                if ($s) { fclose($s); $ok = true; } else { usleep(100000); }
            }
            if (!$ok) { throw new RuntimeException("server on $port did not start"); }
        }
    }

    /** @param array<string,string> $env */
    private static function spawn(string $addr, string $router, array $env, ?string $docroot): void
    {
        $cmd = [PHP_BINARY, '-S', $addr];
        if ($docroot) { array_push($cmd, '-t', $docroot); }
        $cmd[] = $router;
        $p = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', self::$tmp . '/err-' . substr($addr, -5) . '.log', 'w']], $pipes, null, $env);
        if (!is_resource($p)) { throw new RuntimeException('cannot spawn ' . $addr); }
        self::$procs[] = $p;
    }

    /** PHP warnings, notices, deprecations and fatals that either test server wrote to its log during the run. @return list<string> */
    public static function phpProblems(): array
    {
        $out = [];
        foreach (glob(self::$tmp . '/err-*.log') ?: [] as $f) {
            foreach (file($f, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                // The oversized-upload test provokes PHP's own post_max_size warning on purpose; everything else is a bug.
                if (preg_match('/PHP (Warning|Notice|Deprecated|Fatal error|Parse error|Stack trace)/', $line) && !preg_match('/POST Content-Length of \d+ bytes exceeds the limit of \d+ bytes/', $line)) { $out[] = basename($f) . ': ' . trim(preg_replace('/^\[[^\]]*\]\s*/', '', $line)); }
            }
        }
        return array_values(array_unique($out));
    }

    public static function stop(): void
    {
        foreach (self::$procs as $p) { proc_terminate($p); proc_close($p); }
        if (self::$tmp !== '' && is_dir(self::$tmp) && !getenv('KEEP_TEST_LOGS')) {
            exec('rm -rf ' . escapeshellarg(self::$tmp));
        }
    }

    public static function resetWd(): void { Browser::raw('GET', self::WD . '/__reset'); }

    /** @return array{revoked:list<string>,log:list<string>,submits:list<array>,labels:array} */
    public static function wdState(): array { return json_decode(Browser::raw('GET', self::WD . '/__state')['body'], true); }
}

/** Minimal cookie-aware HTTP client. Never follows redirects, so tests assert them explicitly. */
final class Browser
{
    private string $jar;

    public function __construct(private readonly string $base = Servers::APP)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'jar');
    }

    public function __destruct() { @unlink($this->jar); }

    /** @return array{status:int,headers:array<string,string>,body:string,cookies:string} */
    public static function raw(string $method, string $url, ?array $form = null, array $headers = [], ?string $jar = null): array
    {
        $ch = curl_init($url);
        $h = [];
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => function ($c, $l) use (&$h) { $p = strpos($l, ':'); if ($p) { $h[strtolower(trim(substr($l, 0, $p)))] = trim(substr($l, $p + 1)); } return strlen($l); }]);
        if ($jar) { curl_setopt_array($ch, [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]); }
        if ($form !== null) {
            $flat = [];
            $walk = function (array $a, string $prefix = '') use (&$walk, &$flat) {
                foreach ($a as $k => $v) {
                    $key = $prefix === '' ? (string) $k : $prefix . '[' . $k . ']';
                    is_array($v) ? $walk($v, $key) : $flat[$key] = $v;
                }
            };
            $walk($form);
            $multipart = (bool) array_filter($flat, fn ($v) => $v instanceof CURLFile);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $flat : http_build_query($form));
        }
        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'headers' => $h, 'body' => $body, 'cookies' => $jar ? (string) @file_get_contents($jar) : ''];
    }

    /** @return array{status:int,headers:array<string,string>,body:string,cookies:string} */
    public function get(string $path, array $headers = []): array { return self::raw('GET', $this->base . $path, null, $headers, $this->jar); }

    public function post(string $path, array $form = [], bool $withCsrf = true, ?string $csrfFrom = '/login'): array
    {
        if ($withCsrf && !isset($form['_token'])) { $form['_token'] = $this->csrf($csrfFrom ?? '/login'); }
        return self::raw('POST', $this->base . $path, $form, [], $this->jar);
    }

    public function csrf(string $from = '/login'): string
    {
        $r = $this->get($from);
        if (preg_match('/name="_token" value="([a-f0-9]{64})"/', $r['body'], $m) !== 1) { throw new RuntimeException("no csrf token on $from (status {$r['status']})"); }
        return $m[1];
    }

    public function login(string $user = 'alice@example.com', string $pass = 'correct-pass'): array
    {
        return $this->post('/login', ['username' => $user, 'password' => $pass]);
    }

    /** Fetch any authenticated page and pull a CSRF token from its markup. */
    public function pageToken(string $path = '/mail/inbox'): string { return $this->csrf($path); }
}
