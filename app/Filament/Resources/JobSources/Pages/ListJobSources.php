<?php

namespace App\Filament\Resources\JobSources\Pages;

use App\Filament\Resources\JobSources\JobSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJobSources extends ListRecords
{
    protected static string $resource = JobSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
