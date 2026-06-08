<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->status === 'inactive') {
            auth()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('filament.dashboard.auth.login')->withErrors([
                'data.email' => __('Your account has been deactivated. Please contact the administrator.'),
            ]);
        }

        return $next($request);
    }
}
