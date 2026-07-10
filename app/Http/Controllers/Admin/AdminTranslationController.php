<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminTranslationRequest;
use App\Models\TranslationLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTranslationController extends Controller
{
    public function index(Request $request): Response
    {
        $locales = config('app.available_locales', ['en']);
        $filterGroup = $request->string('group')->toString();

        $query = TranslationLine::groupedQuery();

        if ($filterGroup !== '') {
            $query->where('group', $filterGroup);
        }

        $lines = $query->get();

        return Inertia::render('Admin/Translations/Index', [
            'translationLines' => $lines->map(function (TranslationLine $line) use ($locales): array {
                $previews = [];

                foreach ($locales as $locale) {
                    $previews[$locale] = TranslationLine::valueForKeyLocale($line->group, $line->key, $locale);
                }

                return [
                    'id' => $line->id,
                    'group' => $line->group,
                    'key' => $line->key,
                    'full_key' => "{$line->group}.{$line->key}",
                    'previews' => $previews,
                    'updated_at' => $line->updated_at,
                ];
            }),
            'groups' => TranslationLine::query()->distinct()->orderBy('group')->pluck('group'),
            'localeOptions' => collect($locales)
                ->map(fn (string $locale): array => [
                    'value' => $locale,
                    'label' => config("app.locale_labels.{$locale}", strtoupper($locale)),
                ])
                ->values()
                ->all(),
            'filterGroup' => $filterGroup !== '' ? $filterGroup : null,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Translations/Create', $this->formOptions());
    }

    public function store(AdminTranslationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        TranslationLine::syncKey(
            $validated['group'],
            $validated['key'],
            $validated['values'] ?? [],
        );

        return redirect()
            ->route('administration.translations.index')
            ->with('success', __('app.administration.translations_created'));
    }

    public function edit(TranslationLine $translationLine): Response
    {
        return Inertia::render('Admin/Translations/Edit', [
            ...$this->formOptions(),
            'translation' => [
                'id' => $translationLine->id,
                'group' => $translationLine->group,
                'key' => $translationLine->key,
                'full_key' => "{$translationLine->group}.{$translationLine->key}",
                'values' => TranslationLine::valuesForKey($translationLine->group, $translationLine->key),
            ],
        ]);
    }

    public function update(AdminTranslationRequest $request, TranslationLine $translationLine): RedirectResponse
    {
        TranslationLine::syncKey(
            $translationLine->group,
            $translationLine->key,
            $request->validated()['values'] ?? [],
        );

        return redirect()
            ->route('administration.translations.index')
            ->with('success', __('app.administration.translations_updated'));
    }

    public function destroy(TranslationLine $translationLine): RedirectResponse
    {
        TranslationLine::deleteKey($translationLine->group, $translationLine->key);

        return redirect()
            ->route('administration.translations.index')
            ->with('success', __('app.administration.translations_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'localeOptions' => collect(config('app.available_locales', ['en']))
                ->map(fn (string $locale): array => [
                    'value' => $locale,
                    'label' => config("app.locale_labels.{$locale}", strtoupper($locale)),
                ])
                ->values()
                ->all(),
        ];
    }
}
