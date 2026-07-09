<?php

namespace App\Filament\Resources\JobSources\Pages;

use App\Filament\Resources\JobSources\JobSourceResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ConfigureScrape extends Page
{
    use InteractsWithRecord;

    protected static string $resource = JobSourceResource::class;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.job-sources.configure-scrape';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->redirectRoute('job-sources.configure', $this->getRecord());
    }
}
