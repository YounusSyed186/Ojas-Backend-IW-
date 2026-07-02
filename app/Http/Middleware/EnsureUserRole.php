<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (in_array($user->role, $roles, true) || collect($roles)->contains(fn (string $role) => $user->hasRole($role))) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden.'], 403);
    }
}
