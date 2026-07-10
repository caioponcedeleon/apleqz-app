<?php

namespace App\Http\Middleware;

use App\Enums\JobAlertsTier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasJobAlerts
{
    public function handle(Request $request, Closure $next): Response
    {
        $tier = $request->user()?->jobAlertsTier();

        if ($tier === null || $tier === JobAlertsTier::None) {
            abort(403);
        }

        return $next($request);
    }
}
