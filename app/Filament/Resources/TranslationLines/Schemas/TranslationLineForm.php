<?php

namespace App\Filament\Resources\TranslationLines\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TranslationLineForm
{
    public static function configure(Schema $schema): Schema
    {
        $locales = config('app.available_locales', ['en', 'pt']);

        $localeFields = collect($locales)->map(
            fn (string $locale) => Textarea::make("values.{$locale}")
                ->label(self::localeLabel($locale))
                ->rows(4)
                ->columnSpanFull(),
        )->all();

        return $schema
            ->components([
                TextInput::make('group')
                    ->required()
                    ->maxLength(100)
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),
                TextInput::make('key')
                    ->required()
                    ->maxLength(255)
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),
                Section::make('Translations')
                    ->schema($localeFields)
                    ->columnSpanFull(),
            ]);
    }

    protected static function localeLabel(string $locale): string
    {
        return config("app.locale_labels.{$locale}", strtoupper($locale));
    }
}
