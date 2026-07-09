<?php

namespace App\Jobs;

use App\Models\JobSource;
use App\Services\JobScrapeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScrapeJobSourceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public JobSource $jobSource,
    ) {}

    public function handle(JobScrapeService $scraper): void
    {
        if (! $this->jobSource->is_active) {
            return;
        }

        $scraper->scrape($this->jobSource);
    }
}
