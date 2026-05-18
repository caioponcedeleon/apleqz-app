<?php

namespace App\Filament\Resources\TranslationLines\Tables;

use App\Models\TranslationLine;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TranslationLinesTable
{
    public static function configure(Table $table): Table
    {
        $locales = config('app.available_locales', ['en', 'pt']);

        $localeColumns = collect($locales)->map(
            fn (string $locale) => TextColumn::make("preview_{$locale}")
                ->label(strtoupper($locale))
                ->limit(50)
                ->wrap()
                ->state(fn (TranslationLine $record): ?string => TranslationLine::valueForKeyLocale(
                    $record->group,
                    $record->key,
                    $locale,
                )),
        )->all();

        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('Key')
                    ->searchable()
                    ->sortable()
                    ->description(fn (TranslationLine $record): string => "{$record->group}.{$record->key}"),
                ...$localeColumns,
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw('MAX(updated_at) '.$direction);
                    }),
            ])
            ->defaultSort('key')
            ->defaultKeySort(false)
            ->defaultGroup('group')
            ->groups([
                Group::make('group')
                    ->label('Group')
                    ->collapsible(),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->options(fn () => TranslationLine::query()->distinct()->orderBy('group')->pluck('group', 'group')->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->using(fn ($records) => TranslationLine::deleteKeys($records)),
                ]),
            ]);
    }
}
