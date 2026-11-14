<?php
declare(strict_types=1);

namespace Webmail\Core;

final class View
{
    public function __construct(private readonly string $dir)
    {
    }

    /**
     * Renders a view in an isolated scope. The renderer's own locals use a "__" prefix so that no data key
     * (for example "name" or "data") can be silently shadowed.
     *
     * @param array<string,mixed> $data
     */
    public function render(string $name, array $data = []): string
    {
        $file = $this->dir . '/' . str_replace('.', '/', $name) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $name");
        }
        return (function (string $__file, array $__data, self $view): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                include $__file;
            } catch (\Throwable $t) {
                ob_end_clean();
                throw $t;
            }
            return (string) ob_get_clean();
        })($file, $data, $this);
    }
}
