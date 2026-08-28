<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AuditLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();
        $method = $request->method();

        if (! $this->shouldAudit($routeName, $method)) {
            return $next($request);
        }

        $startedAt = microtime(true);

        $response = $next($request);

        Log::channel('audit')->info('HTTP_ACTION', [
            'route' => $routeName,
            'method' => $method,
            'uri' => $request->route()?->uri(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round(
                (microtime(true) - $startedAt) * 1000,
                2
            ),
        ]);

        return $response;
    }

    private function shouldAudit(
        ?string $routeName,
        string $method
    ): bool {
        if ($routeName === null) {
            return false;
        }

        if (! in_array(
            $method,
            config('audit.methods', []),
            true
        )) {
            return false;
        }

        foreach (config('audit.routes', []) as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
