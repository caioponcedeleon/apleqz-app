<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeJobSourceJob;
use App\Models\JobSource;
use Illuminate\Console\Command;

class ScrapeJobSourcesCommand extends Command
{
    protected $signature = 'jobs:scrape-sources';

    protected $description = 'Dispatch scrape jobs for all active job sources';

    public function handle(): int
    {
        $sources = JobSource::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($sources->isEmpty()) {
            $this->info('No active job sources to scrape.');

            return self::SUCCESS;
        }

        foreach ($sources as $source) {
            ScrapeJobSourceJob::dispatch($source);
            $this->line("Dispatched scrape for {$source->name}");
        }

        $this->info("Dispatched {$sources->count()} scrape job(s).");

        return self::SUCCESS;
    }
}
