<?php

namespace App\Http\Middleware;

use App\Support\TenantAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = Auth::guard('tenant')->user();
 
        if (! $user) {
            return redirect()->route('login'); // sesuaikan nama route login kamu
        }
 
        if (! Gate::forUser($user)->allows($ability)) {
            return redirect()->route(TenantAccess::homeRouteFor($user));
        }
 
        return $next($request);
    }
}
