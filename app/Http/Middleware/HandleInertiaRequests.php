<?php

namespace App\Http\Middleware;

use App\Services\TranslationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $translationService = app(TranslationService::class);
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'locale' => $locale,
            'locales' => $translationService->availableLocales(),
            'translations' => $translationService->translationsForLocale($locale),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
