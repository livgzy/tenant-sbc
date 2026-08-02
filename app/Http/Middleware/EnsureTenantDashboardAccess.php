<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class EnsureTenantDashboardAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('tenant')->user();

        if (! Gate::forUser($user)->allows('tenant-dashboard-access')) {
            return redirect()->route('tenant.reservation'); // ke /reservasi
        }

        return $next($request);
    }
}
