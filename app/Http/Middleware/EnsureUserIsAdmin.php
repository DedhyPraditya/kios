<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            \App\Models\ActivityLog::record(
                'auth.forbidden',
                "Mencoba membuka halaman khusus admin: {$request->method()} /{$request->path()}",
                null,
                ['path' => $request->path(), 'user_agent' => substr((string) $request->userAgent(), 0, 200)]
            );
            abort(403, 'Khusus admin.');
        }

        return $next($request);
    }
}
