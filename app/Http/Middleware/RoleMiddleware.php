<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/*
 * Use as role:admin,subadmin on a route group. With no roles ("role") it only makes sure the login is still switched on.
 * A switched-off login is signed out on its next click.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }
        if (!$user->active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['login' => 'This login has been switched off. Please contact the administrator.']);
        }
        if ($user->role === 'subadmin' && !config('rentstock.subadmin_enabled')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['login' => 'This login is not available right now. Please contact the administrator.']);
        }
        if ($roles && !in_array($user->role, $roles, true)) {
            abort(403, 'You do not have access to this page.');
        }
        return $next($request);
    }
}
