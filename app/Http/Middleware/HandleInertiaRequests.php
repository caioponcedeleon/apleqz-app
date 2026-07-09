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
        $waves = $user
            ? $user->applicationWaves()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'is_default'])
            : [];

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'locale' => $locale,
            'locales' => $translationService->availableLocales(),
            'localeLabels' => collect(config('app.locale_labels', []))
                ->only($translationService->availableLocales())
                ->all(),
            'translations' => $translationService->translationsForLocale($locale),
            'waves' => $waves,
            'selectedWave' => $selectedWave
                ? $selectedWave->only(['id', 'name', 'is_default'])
                : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'onboarding' => $user
                ? [
                    'show' => $user->onboarding_completed_at === null,
                    'hasWaves' => $waves->isNotEmpty(),
                    'manageApplicationUuid' => $user->applications()->orderByDesc('updated_at')->value('uuid'),
                ]
                : null,
        ];
    }
}
