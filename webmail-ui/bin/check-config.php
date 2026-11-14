#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Webmail\Core\Env;
use Webmail\Support\ConfigCheck;

$opts = getopt('', ['php-ini::', 'no-network', 'env-file::', 'strict', 'help']);
if (isset($opts['help'])) {
    fwrite(STDOUT, "Usage: php bin/check-config.php [--php-ini=FILE] [--env-file=FILE] [--no-network] [--strict]\n"
        . "  --php-ini   evaluate upload/memory/opcache settings from this ini file (the PHP-FPM one) instead of the CLI's\n"
        . "  --no-network  skip the WildDuck reachability / token check\n  --strict    treat warnings as failures\n");
    exit(0);
}
Env::load((string) ($opts['env-file'] ?? dirname(__DIR__) . '/.env'));

$findings = ConfigCheck::run([
    'php_ini' => isset($opts['php-ini']) && is_string($opts['php-ini']) ? $opts['php-ini'] : null,
    'network' => !isset($opts['no-network']),
    'env_file' => isset($opts['env-file']) && is_string($opts['env-file']) ? $opts['env-file'] : null,
]);

$tag = ['ok' => '[ OK ]', 'warn' => '[WARN]', 'fail' => '[FAIL]'];
$counts = ['ok' => 0, 'warn' => 0, 'fail' => 0];
foreach ($findings as $f) {
    $counts[$f['level']]++;
    fwrite(STDOUT, $tag[$f['level']] . ' ' . $f['text'] . "\n");
}
fwrite(STDOUT, sprintf("\n%d ok, %d warnings, %d failures\n", $counts['ok'], $counts['warn'], $counts['fail']));
exit($counts['fail'] > 0 || (isset($opts['strict']) && $counts['warn'] > 0) ? 1 : 0);
