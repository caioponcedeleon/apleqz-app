<?php

namespace App\Jobs;

use App\Services\JobMatchBackfillService;
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

    public function handle(JobMatchRunTracker $tracker, JobMatchBackfillService $backfill): void
    {
        if ($tracker->hasPendingScrapeJobs()) {
            $this->release(30);

            return;
        }

        $backfill->dispatchPending();

        SendJobDigestsAfterMatchRunJob::dispatch();
    }
}
