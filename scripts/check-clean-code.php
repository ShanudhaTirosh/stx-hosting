<?php
declare(strict_types=1);

/**
 * CI Clean-Code Quality Gate.
 * Fails if forbidden TODO/FIXME/HACK/XXX, fake data, or mock markers appear in production code.
 */

$root = dirname(__DIR__);

$targetDirectories = [
    $root . '/app',
    $root . '/resources/views',
    $root . '/routes',
    $root . '/webmail-ui/src',
    $root . '/webmail-ui/views',
];

$forbiddenPatterns = [
    'TODO marker' => '/\b(TODO|FIXME|HACK|XXX|todo|fixme)\b/',
    'Mock data' => '/\b(mock_data|dummy_data|fake_data|placeholder_response)\b/i',
];

$allowedOverrides = [
    // Database migration files that were already committed cannot be edited (protected by AGENTS.md)
];

$violations = [];

foreach ($targetDirectories as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['php', 'js', 'ts'], true)) {
            continue;
        }

        $path = $file->getPathname();
        $relPath = str_replace('\\', '/', substr($path, strlen($root) + 1));

        // Skip test files
        if (str_contains($relPath, 'tests/') || str_contains($relPath, '.test.')) {
            continue;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $lineNum => $line) {
            $trimmed = trim($line);
            foreach ($forbiddenPatterns as $name => $pattern) {
                if (preg_match($pattern, $trimmed)) {
                    $violations[] = [
                        'file' => $relPath,
                        'line' => $lineNum + 1,
                        'type' => $name,
                        'content' => substr($trimmed, 0, 140),
                    ];
                }
            }
        }
    }
}

if (!empty($violations)) {
    echo "\033[31m[FAILED] Clean-code check found " . count($violations) . " violation(s):\033[0m\n\n";
    foreach ($violations as $v) {
        printf("  - %s:%d [%s] %s\n", $v['file'], $v['line'], $v['type'], $v['content']);
    }
    echo "\nPlease remove all TODOs, stubs, and mock data before committing to production.\n";
    exit(1);
}

echo "\033[32m[PASSED] Clean-code check: 0 forbidden markers found across production directories.\033[0m\n";
exit(0);
