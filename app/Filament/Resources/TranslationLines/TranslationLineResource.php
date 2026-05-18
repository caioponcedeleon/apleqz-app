<?php

namespace App\Filament\Resources\TranslationLines;

use App\Filament\Resources\TranslationLines\Pages\CreateTranslationLine;
use App\Filament\Resources\TranslationLines\Pages\EditTranslationLine;
use App\Filament\Resources\TranslationLines\Pages\ListTranslationLines;
use App\Filament\Resources\TranslationLines\Schemas\TranslationLineForm;
use App\Filament\Resources\TranslationLines\Tables\TranslationLinesTable;
use App\Models\TranslationLine;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TranslationLineResource extends Resource
{
    protected static ?string $model = TranslationLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TranslationLineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TranslationLinesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTranslationLines::route('/'),
            'create' => CreateTranslationLine::route('/create'),
            'edit' => EditTranslationLine::route('/{record}/edit'),
        ];
    }
}
