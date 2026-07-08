<?php

declare(strict_types=1);

namespace Milpa\Runtime\Http;

use Milpa\Http\HttpMethod;
use Milpa\Http\Routing\Route;
use Milpa\Http\Routing\RouteResult;
use Milpa\Http\Routing\RouterInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Minimal {@see RouterInterface} implementation: exact segments plus single-segment
 * `{placeholder}`s. Never throws, never returns null — {@see RouteResult} carries the
 * outcome, exactly as the published contract demands.
 *
 * Ported (not reimplemented from scratch) from `example-agent-ready-blog`'s
 * `App\Http\Router` — `milpa/http` ships the `RouterInterface` contract and the route/result
 * value objects but no concrete matcher, and the example already proved this exact shape
 * against the real published types. See the front's report for why this is a port and not
 * a `milpa/http` dependency.
 */
final class Router implements RouterInterface
{
    /** @var list<Route> */
    private readonly array $routes;

    public function __construct(Route ...$routes)
    {
        $this->routes = array_values($routes);
    }

    /**
     * Resolves the request to a typed result — always MATCHED, NOT_FOUND or
     * METHOD_NOT_ALLOWED, never null.
     */
    public function match(ServerRequestInterface $request): RouteResult
    {
        $path = rtrim($request->getUri()->getPath(), '/') ?: '/';
        $method = HttpMethod::tryFrom(strtoupper($request->getMethod()));
        $allowedElsewhere = [];

        foreach ($this->routes as $route) {
            $params = $this->pathParams($route->path, $path);
            if ($params === null) {
                continue;
            }
            if ($method !== null && $route->allows($method)) {
                return RouteResult::matched($route, $params);
            }
            foreach ($route->methods as $allowed) {
                $allowedElsewhere[] = $allowed;
            }
        }

        return $allowedElsewhere === []
            ? RouteResult::notFound()
            : RouteResult::methodNotAllowed($allowedElsewhere);
    }

    /** @return array<string, string>|null Parameters when the path matches, null otherwise. */
    private function pathParams(string $pattern, string $path): ?array
    {
        $patternParts = explode('/', trim($pattern, '/'));
        $pathParts = explode('/', trim($path, '/'));
        if (\count($patternParts) !== \count($pathParts)) {
            return null;
        }
        $params = [];
        foreach ($patternParts as $i => $part) {
            if (preg_match('/^\{(\w+)\}$/', $part, $m) === 1) {
                $params[$m[1]] = $pathParts[$i];
            } elseif ($part !== $pathParts[$i]) {
                return null;
            }
        }

        return $params;
    }
}
