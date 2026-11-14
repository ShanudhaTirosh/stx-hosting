<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$only = $argv[1] ?? null;
foreach (glob(__DIR__ . '/*Test.php') as $f) { require $f; }

$needServers = true;
if ($needServers) { Servers::start(); register_shutdown_function([Servers::class, 'stop']); }

$t0 = microtime(true);
foreach ($GLOBALS['__tests'] as $name => $fn) {
    if ($only && !str_contains($name, $only)) { continue; }
    try {
        $fn();
        $GLOBALS['__pass']++;
        echo "  \033[32mPASS\033[0m $name\n";
    } catch (SkipTest $e) {
        $GLOBALS['__skip'] = ($GLOBALS['__skip'] ?? 0) + 1;
        echo "  \033[33mSKIP\033[0m $name — " . $e->getMessage() . "\n";
    } catch (Throwable $e) {
        $GLOBALS['__fail']++;
        echo "  \033[31mFAIL\033[0m $name\n        " . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
    }
}
$problems = $only ? [] : Servers::phpProblems();
foreach ($problems as $p) { echo "  \033[31mPHP PROBLEM\033[0m $p\n"; }
if ($problems) { $GLOBALS['__fail']++; }
printf("\n%d passed, %d failed%s in %.1fs\n", $GLOBALS['__pass'], $GLOBALS['__fail'], ($GLOBALS['__skip'] ?? 0) ? ', ' . $GLOBALS['__skip'] . ' skipped' : '', microtime(true) - $t0);
exit($GLOBALS['__fail'] > 0 ? 1 : 0);
