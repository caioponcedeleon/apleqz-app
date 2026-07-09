<?php

namespace App\Filament\Resources\JobSources\Pages;

use App\Filament\Resources\JobSources\JobSourceResource;
use App\Support\JobExtractionConfigValidator;
use Filament\Resources\Pages\CreateRecord;

class CreateJobSource extends CreateRecord
{
    protected static string $resource = JobSourceResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        app(JobExtractionConfigValidator::class)->validate(
            $data['extraction_config'] ?? [],
            (bool) ($data['is_active'] ?? false),
        );

        $data['config_version'] = 1;

        return $data;
    }
}
