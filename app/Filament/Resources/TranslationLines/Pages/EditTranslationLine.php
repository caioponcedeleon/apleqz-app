<?php

namespace App\Filament\Resources\TranslationLines\Pages;

use App\Filament\Resources\TranslationLines\TranslationLineResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTranslationLine extends EditRecord
{
    protected static string $resource = TranslationLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
