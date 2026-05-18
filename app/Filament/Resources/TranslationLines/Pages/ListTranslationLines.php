<?php

namespace App\Filament\Resources\TranslationLines\Pages;

use App\Filament\Resources\TranslationLines\TranslationLineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTranslationLines extends ListRecords
{
    protected static string $resource = TranslationLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
