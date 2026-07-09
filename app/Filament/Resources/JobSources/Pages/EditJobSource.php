<?php

namespace App\Filament\Resources\JobSources\Pages;

use App\Filament\Resources\JobSources\JobSourceResource;
use App\Models\JobSource;
use App\Support\JobExtractionConfigValidator;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJobSource extends EditRecord
{
    protected static string $resource = JobSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('configure')
                ->label('Configure extraction')
                ->url(fn (): string => route('job-sources.configure', $this->getRecord())),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        app(JobExtractionConfigValidator::class)->validate(
            $data['extraction_config'] ?? [],
            (bool) ($data['is_active'] ?? false),
        );

        /** @var JobSource $record */
        $record = $this->getRecord();

        if (($data['extraction_config'] ?? null) !== $record->extraction_config) {
            $data['config_version'] = ($record->config_version ?? 0) + 1;
        }

        return $data;
    }
}
