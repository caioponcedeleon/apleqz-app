<?php

namespace App\Filament\Resources\Applications\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.email')->label('User')->searchable()->sortable(),
                TextColumn::make('position')->searchable()->sortable(),
                TextColumn::make('company')->searchable(),
                TextColumn::make('area.name')->label('Area'),
                TextColumn::make('applied_at')->date()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('interview_date')->date()->toggleable(),
            ])
            ->defaultSort('applied_at', 'desc')
            ->filters([
                SelectFilter::make('user_id')->relationship('user', 'email')->searchable(),
                SelectFilter::make('status')->options(
                    collect(\App\Enums\ApplicationStatus::cases())->mapWithKeys(
                        fn ($s) => [$s->value => $s->value]
                    )->all()
                ),
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
