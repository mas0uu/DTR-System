<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs([
            'profile.edit',
            'profile.update',
            'password.update',
            'logout',
        ])) {
            return $next($request);
        }

        return redirect()
            ->route('profile.edit')
            ->with('success', 'For security, please update your password before continuing.');
    }
}

