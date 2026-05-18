<?php

namespace App\Filament\Resources\TranslationLines\Pages;

use App\Filament\Resources\TranslationLines\TranslationLineResource;
use App\Models\TranslationLine;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class EditTranslationLine extends EditRecord
{
    protected static string $resource = TranslationLineResource::class;

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();

        return "{$record->group}.{$record->key}";
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->action(function (): void {
                    TranslationLine::deleteKey($this->getRecord()->group, $this->getRecord()->key);

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        $data['values'] = TranslationLine::valuesForKey($record->group, $record->key);

        foreach (config('app.available_locales', ['en', 'pt']) as $locale) {
            $data['values'][$locale] ??= '';
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $values = $data['values'] ?? [];

        return TranslationLine::syncKey($record->group, $record->key, $values);
    }
}
