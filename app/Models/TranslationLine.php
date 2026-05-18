<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TranslationLine extends Model
{
    protected $fillable = [
        'group',
        'key',
        'locale',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    public static function valuesForKey(string $group, string $key): array
    {
        return static::query()
            ->where('group', $group)
            ->where('key', $key)
            ->pluck('value', 'locale')
            ->all();
    }

    /**
     * @param  array<string, string>  $values
     */
    public static function syncKey(string $group, string $key, array $values): static
    {
        $representative = null;

        foreach (config('app.available_locales', ['en', 'pt']) as $locale) {
            $line = static::query()->updateOrCreate(
                ['group' => $group, 'key' => $key, 'locale' => $locale],
                ['value' => $values[$locale] ?? ''],
            );

            $representative ??= $line;
        }

        return $representative;
    }

    public static function deleteKey(string $group, string $key): void
    {
        static::query()
            ->where('group', $group)
            ->where('key', $key)
            ->delete();
    }

    public static function groupedQuery(): Builder
    {
        return static::query()
            ->selectRaw('MIN(id) as id')
            ->addSelect(['group', 'key'])
            ->selectRaw('MAX(updated_at) as updated_at')
            ->groupBy('group', 'key')
            ->orderBy('group')
            ->orderBy('key');
    }

    public static function valueForKeyLocale(string $group, string $key, string $locale): ?string
    {
        return static::query()
            ->where('group', $group)
            ->where('key', $key)
            ->where('locale', $locale)
            ->value('value');
    }

    /**
     * @param  Collection<int, static>  $records
     */
    public static function deleteKeys(Collection $records): void
    {
        foreach ($records as $record) {
            static::deleteKey($record->group, $record->key);
        }
    }
}
