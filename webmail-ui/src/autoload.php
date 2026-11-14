<?php
declare(strict_types=1);

// Minimal PSR-4 autoloader: namespace Webmail\ => src/
spl_autoload_register(static function (string $class): void {
    $prefix = 'Webmail\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/Support/helpers.php';
