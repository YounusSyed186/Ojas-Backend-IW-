<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCommerceCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->role !== 'customer' || $user->status !== 'active') return response()->json(['message' => 'An active customer account is required.'], 403);
        if (! $user->phone_verified_at) return response()->json(['message' => 'Phone verification is required.'], 403);
        return $next($request);
    }
}
