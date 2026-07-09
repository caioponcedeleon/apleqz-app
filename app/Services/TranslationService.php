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
        $locale = filled($userLocale)
            ? $userLocale
            : ($sessionLocale ?? config('app.locale'));

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
        return Cache::remember($this->cacheKey($locale), 3600, function () use ($locale) {
            $fileTranslations = $this->loadFileTranslations($locale);
            $dbTranslations = $this->loadDatabaseTranslations($locale);

            return array_replace_recursive($fileTranslations, $dbTranslations);
        });
    }

    public function syncAllFromFiles(): int
    {
        $count = 0;

        foreach ($this->availableLocales() as $locale) {
            $count += $this->seedFromFiles($locale);
        }

        return $count;
    }

    public function flush(string $locale): void
    {
        Cache::forget($this->cacheKey($locale));
        Cache::forget("translations.{$locale}");
    }

    public function flushAll(): void
    {
        foreach ($this->availableLocales() as $locale) {
            $this->flush($locale);
        }
    }

    protected function cacheKey(string $locale): string
    {
        return 'translations.'.$locale.'.'.$this->fileRevisionHash($locale);
    }

    protected function fileRevisionHash(string $locale): string
    {
        $path = lang_path($locale);

        if (! File::isDirectory($path)) {
            return 'empty';
        }

        $parts = [];

        foreach (File::files($path) as $file) {
            $parts[] = $file->getFilename().':'.filemtime($file->getPathname());
        }

        sort($parts);

        return md5(implode('|', $parts));
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
            $group = $line->group;
            $segments = explode('.', $line->key);

            if (! isset($translations[$group])) {
                $translations[$group] = [];
            }

            $this->setNestedValue($translations[$group], $segments, $line->value);
        }

        return $translations;
    }

    /**
     * @param  array<string, mixed>  $array
     * @param  list<string>  $segments
     */
    protected function setNestedValue(array &$array, array $segments, string $value): void
    {
        $current = &$array;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $current[$segment] = $value;

                return;
            }

            if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }
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
