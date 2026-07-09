<?php

namespace App\Filament\Resources\JobSources;

use App\Filament\Resources\JobSources\Pages\CreateJobSource;
use App\Filament\Resources\JobSources\Pages\EditJobSource;
use App\Filament\Resources\JobSources\Pages\ListJobSources;
use App\Filament\Resources\JobSources\Schemas\JobSourceForm;
use App\Filament\Resources\JobSources\Tables\JobSourcesTable;
use App\Models\JobSource;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class JobSourceResource extends Resource
{
    protected static ?string $model = JobSource::class;

    protected static ?string $navigationLabel = 'Job sources';

    protected static ?string $modelLabel = 'job source';

    protected static ?string $pluralModelLabel = 'job sources';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|\UnitEnum|null $navigationGroup = 'Job alerts';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return JobSourceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobSourcesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobSources::route('/'),
            'create' => CreateJobSource::route('/create'),
            'edit' => EditJobSource::route('/{record}/edit'),
        ];
    }
}
