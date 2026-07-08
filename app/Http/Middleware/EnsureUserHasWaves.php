<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasWaves
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->applicationWaves()->doesntExist()) {
            return redirect()
                ->route('waves.index')
                ->with('error', __('app.flash.application_requires_wave'));
        }

        return $next($request);
    }
}
