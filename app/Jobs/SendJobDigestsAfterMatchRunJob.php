<?php

namespace App\Jobs;

use App\Services\JobDigestDispatchService;
use App\Services\JobMatchRunTracker;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendJobDigestsAfterMatchRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 120;

    public function retryUntil(): DateTime
    {
        return now()->addHour();
    }

    public function handle(JobDigestDispatchService $dispatch, JobMatchRunTracker $tracker): void
    {
        if ($tracker->hasPendingMatchRelatedJobs()) {
            $this->release(15);

            return;
        }

        $dispatch->sendPendingDigests();
    }
}
