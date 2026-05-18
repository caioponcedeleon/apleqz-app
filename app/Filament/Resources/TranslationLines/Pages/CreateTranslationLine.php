<?php

namespace App\Filament\Resources\TranslationLines\Pages;

use App\Filament\Resources\TranslationLines\TranslationLineResource;
use App\Models\TranslationLine;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateTranslationLine extends CreateRecord
{
    protected static string $resource = TranslationLineResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        if (TranslationLine::query()->where('group', $data['group'])->where('key', $data['key'])->exists()) {
            throw ValidationException::withMessages([
                'data.key' => __('This translation key already exists for the selected group.'),
            ]);
        }

        $values = $data['values'] ?? [];

        return TranslationLine::syncKey($data['group'], $data['key'], $values);
    }
}
