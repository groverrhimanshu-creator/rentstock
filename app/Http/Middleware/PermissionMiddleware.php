<?php

namespace App\Http\Middleware;

use App\Support\Perm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/* Use as perm:rental.create on a route, or perm:rental.edit|replacement.create to allow any of several. The Admin passes every check. */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $keys): Response
    {
        abort_unless(Perm::has($keys, $request->user()), 403, 'You do not have permission to do this. Please ask the administrator.');
        return $next($request);
    }
}
