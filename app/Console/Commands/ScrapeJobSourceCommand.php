<?php

namespace App\Console\Commands;

use App\Enums\JobScrapeStatus;
use App\Models\JobSource;
use App\Services\JobScrapeService;
use Illuminate\Console\Command;

class ScrapeJobSourceCommand extends Command
{
    protected $signature = 'jobs:scrape-source {jobSource : Job source UUID}';

    protected $description = 'Scrape a single job source immediately (for admin/testing)';

    public function handle(JobScrapeService $scraper): int
    {
        $source = JobSource::query()->findOrFail($this->argument('jobSource'));

        $this->info("Scraping {$source->name}...");

        $run = $scraper->scrape($source);

        if ($run->status === JobScrapeStatus::Success) {
            $this->info("Done. Found {$run->listings_found} listing(s), {$run->listings_new} new.");

            return self::SUCCESS;
        }

        $this->error('Scrape failed: '.($run->error_message ?? 'Unknown error'));

        return self::FAILURE;
    }
}
