<?php

namespace App\Services;

use App\Models\TranslationLine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class TranslationService
{
    public function localeForRequest(?string $sessionLocale, ?string $userLocale): string
    {
        $locale = $sessionLocale ?? $userLocale ?? config('app.locale');

        if (! in_array($locale, $this->availableLocales(), true)) {
            return config('app.locale');
        }

        return $locale;
    }

    public function availableLocales(): array
    {
        return config('app.available_locales', ['en', 'pt']);
    }

    public function translationsForLocale(string $locale): array
    {
        return Cache::remember("translations.{$locale}", 3600, function () use ($locale) {
            $fileTranslations = $this->loadFileTranslations($locale);
            $dbTranslations = $this->loadDatabaseTranslations($locale);

            return array_replace_recursive($fileTranslations, $dbTranslations);
        });
    }

    public function flush(string $locale): void
    {
        Cache::forget("translations.{$locale}");
    }

    public function flushAll(): void
    {
        foreach ($this->availableLocales() as $locale) {
            $this->flush($locale);
        }
    }

    protected function loadFileTranslations(string $locale): array
    {
        $path = lang_path($locale);

        if (! File::isDirectory($path)) {
            return [];
        }

        $translations = [];

        foreach (File::files($path) as $file) {
            $group = $file->getFilenameWithoutExtension();
            $translations[$group] = require $file->getPathname();
        }

        return $translations;
    }

    protected function loadDatabaseTranslations(string $locale): array
    {
        if (! Schema::hasTable('translation_lines')) {
            return [];
        }

        $lines = TranslationLine::query()->where('locale', $locale)->get();
        $translations = [];

        foreach ($lines as $line) {
            $translations[$line->group][$line->key] = $line->value;
        }

        return $translations;
    }

    public function seedFromFiles(string $locale): int
    {
        $fileTranslations = $this->loadFileTranslations($locale);
        $count = 0;

        foreach ($fileTranslations as $group => $keys) {
            $this->flattenKeys($keys, '', function (string $key, string $value) use ($group, $locale, &$count) {
                TranslationLine::query()->updateOrCreate(
                    ['group' => $group, 'key' => $key, 'locale' => $locale],
                    ['value' => $value]
                );
                $count++;
            });
        }

        $this->flush($locale);

        return $count;
    }

    protected function flattenKeys(array $array, string $prefix, callable $callback): void
    {
        foreach ($array as $key => $value) {
            $fullKey = $prefix === '' ? $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $this->flattenKeys($value, $fullKey, $callback);
            } else {
                $callback($fullKey, (string) $value);
            }
        }
    }
}
