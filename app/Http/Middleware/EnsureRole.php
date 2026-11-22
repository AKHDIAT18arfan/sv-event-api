<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemakaian di route: ->middleware('role:panitia,super_admin')
 * Pastikan middleware auth:sanctum dipasang lebih dulu.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            throw new ApiException('FORBIDDEN', 'Kamu tidak punya akses ke halaman ini.', 403);
        }

        return $next($request);
    }
}
