<?php
declare(strict_types=1);

/** Runs the browser-script tests (tests/js, Node + jsdom): the real public/assets/*.js executed against a real DOM. */
test('js: all browser scripts behave correctly in a DOM (notify, bulk, suggest, editor, compose, app, theme)', function () {
    $root = dirname(__DIR__);
    $node = trim((string) shell_exec('command -v node'));
    if ($node === '') { skip('node is not installed'); }
    if (!is_dir($root . '/node_modules/jsdom')) { skip('run "npm install" to enable the browser-script tests'); }
    $files = glob($root . '/tests/js/*.test.js') ?: [];
    $cmd = escapeshellarg($node) . ' --test-reporter=tap --test ' . implode(' ', array_map('escapeshellarg', $files)) . ' 2>&1';
    exec('cd ' . escapeshellarg($root) . ' && ' . $cmd, $out, $code);
    $text = implode("\n", $out);
    preg_match('/[#ℹ]\s*pass (\d+)/u', $text, $p); preg_match('/[#ℹ]\s*fail (\d+)/u', $text, $f);
    if ($code !== 0 || ($f[1] ?? '0') !== '0') {
        preg_match_all('/^not ok .*$/m', $text, $bad);
        throw new AssertionError('JS tests failed: ' . implode(' | ', array_slice($bad[0], 0, 5)));
    }
    check((int) ($p[1] ?? 0) >= 30, 'expected the full JS suite to run, ran ' . ($p[1] ?? '0'));
});
