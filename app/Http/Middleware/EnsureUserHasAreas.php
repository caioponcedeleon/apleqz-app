<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasAreas
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->areas()->doesntExist()) {
            return redirect()
                ->route('areas.index')
                ->with('error', __('app.flash.application_requires_area'));
        }

        return $next($request);
    }
}
