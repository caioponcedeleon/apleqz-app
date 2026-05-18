<?php

namespace App\Filament\Resources\TranslationLines\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TranslationLinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('locale')->badge()->sortable(),
                TextColumn::make('group')->searchable()->sortable(),
                TextColumn::make('key')->searchable(),
                TextColumn::make('value')->limit(60)->wrap(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('group')
            ->filters([
                SelectFilter::make('locale')
                    ->options(array_combine(config('app.available_locales'), config('app.available_locales'))),
                SelectFilter::make('group')
                    ->options(fn () => \App\Models\TranslationLine::query()->distinct()->pluck('group', 'group')->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
