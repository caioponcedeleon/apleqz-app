<?php

namespace App\Http\Middleware;

use App\Services\SelectedWaveService;
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
        $user = $request->user();
        $selectedWave = $user
            ? app(SelectedWaveService::class)->forRequest($request, $user)
            : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'locale' => $locale,
            'locales' => $translationService->availableLocales(),
            'translations' => $translationService->translationsForLocale($locale),
            'waves' => fn () => $user
                ? $user->applicationWaves()
                    ->orderByDesc('is_default')
                    ->orderBy('name')
                    ->get(['id', 'name', 'is_default'])
                : [],
            'selectedWave' => fn () => $selectedWave
                ? $selectedWave->only(['id', 'name', 'is_default'])
                : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
