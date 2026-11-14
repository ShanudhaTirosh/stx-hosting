<?php
declare(strict_types=1);

// Built-in server: let it serve real static files itself.
if (PHP_SAPI === 'cli-server') {
    $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($p !== '/' && is_file(__DIR__ . $p) && !str_ends_with($p, '.php')) {
        return false;
    }
}

require dirname(__DIR__) . '/src/autoload.php';

ini_set('display_errors', '0');
try {
    $app = new Webmail\App(dirname(__DIR__));
} catch (Throwable $e) {
    error_log('[webmail] boot failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Webmail is not configured correctly. Check the server log.');
}
$app->handle(Webmail\Core\Request::fromGlobals())->send();
