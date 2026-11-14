<?php
declare(strict_types=1);

namespace Webmail\Core;

final class Router
{
    /** @var list<array{string,string,callable,list<string>}> */
    private array $routes = [];

    /** @param list<string> $middleware */
    public function add(string $method, string $pattern, callable $handler, array $middleware = []): void
    {
        $this->routes[] = [$method, $pattern, $handler, $middleware];
    }

    /** @return array{callable,array<string,string>,list<string>}|int  int = 404 or 405 */
    public function match(string $method, string $path): array|int
    {
        $pathMatched = false;
        foreach ($this->routes as [$m, $pattern, $handler, $mw]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $mt) === 1) {
                $pathMatched = true;
                if ($m === $method) {
                    $params = array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY);
                    return [$handler, array_map('rawurldecode', $params), $mw];
                }
            }
        }
        return $pathMatched ? 405 : 404;
    }
}
