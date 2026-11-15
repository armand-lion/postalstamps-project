<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Alleen ingelogde beheerders hebben toegang.
        abort_unless(
            $request->user()?->is_admin === true,
            403,
            __('Je hebt geen toestemming om deze pagina te bekijken.')
        );

        return $next($request);
    }
}
