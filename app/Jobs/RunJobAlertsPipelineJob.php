<?php

namespace App\Jobs;

use App\Services\JobMatchRunTracker;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunJobAlertsPipelineJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 120;

    public function retryUntil(): DateTime
    {
        return now()->addHours(2);
    }

    public function handle(JobMatchRunTracker $tracker): void
    {
        if ($tracker->hasPendingScrapeJobs()) {
            $this->release(30);

            return;
        }

        // Matching is handled per scrape via MatchNewListingsJob (new listings only).
        SendJobDigestsAfterMatchRunJob::dispatch();
    }
}
