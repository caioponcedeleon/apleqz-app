<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function complete(Request $request): RedirectResponse
    {
        $request->user()->update([
            'onboarding_completed_at' => now(),
        ]);

        return back();
    }
}
