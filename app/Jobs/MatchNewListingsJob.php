<?php

namespace App\Jobs;

use App\Models\JobListing;
use App\Models\UserJobSourceSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MatchNewListingsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $listingIds
     */
    public function __construct(
        public array $listingIds,
    ) {}

    public function handle(): void
    {
        $listingIds = collect($this->listingIds)->filter()->unique()->values();

        if ($listingIds->isEmpty()) {
            return;
        }

        $listings = JobListing::query()
            ->whereIn('id', $listingIds)
            ->get(['id', 'job_source_id']);

        foreach ($listings as $listing) {
            $userIds = UserJobSourceSubscription::query()
                ->where('job_source_id', $listing->job_source_id)
                ->where('is_active', true)
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                EvaluateJobMatchJob::dispatch($userId, $listing->id);
            }
        }
    }
}
