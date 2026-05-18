<?php

namespace App\Filament\Resources\TranslationLines\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TranslationLineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('group')->required()->maxLength(100),
                TextInput::make('key')->required()->maxLength(255),
                Select::make('locale')
                    ->options(array_combine(config('app.available_locales'), config('app.available_locales')))
                    ->required(),
                Textarea::make('value')->required()->rows(4)->columnSpanFull(),
            ]);
    }
}
