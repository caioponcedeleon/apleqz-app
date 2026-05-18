<?php

namespace App\Http\Controllers;

use App\Services\TranslationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, TranslationService $translations): RedirectResponse
    {
        $locale = $request->string('locale')->toString();

        if (! in_array($locale, $translations->availableLocales(), true)) {
            abort(400);
        }

        $request->session()->put('locale', $locale);

        if ($request->user()) {
            $request->user()->update(['locale' => $locale]);
        }

        return back();
    }
}
