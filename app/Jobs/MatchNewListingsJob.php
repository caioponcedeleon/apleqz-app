<?php

namespace App\Jobs;

use App\Enums\JobAlertsTier;
use App\Enums\JobMatchStatus;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\UserJobSourceSubscription;
use App\Services\JobMatchApplicationOverlapChecker;
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
        public ?JobAlertsTier $tierFilter = null,
    ) {}

    public function handle(JobMatchApplicationOverlapChecker $applicationOverlap): void
    {
        $listingIds = collect($this->listingIds)->filter()->unique()->values();

        if ($listingIds->isEmpty()) {
            return;
        }

        $listings = JobListing::query()
            ->whereIn('id', $listingIds)
            ->get(['id', 'job_source_id', 'title', 'url']);

        foreach ($listings as $listing) {
            $userQuery = UserJobSourceSubscription::query()
                ->where('job_source_id', $listing->job_source_id)
                ->where('is_active', true)
                ->whereHas('user', function ($query): void {
                    $tiers = $this->tierFilter !== null
                        ? [$this->tierFilter->value]
                        : [JobAlertsTier::Regex->value, JobAlertsTier::Ai->value];

                    $query->whereIn('job_alerts_tier', $tiers);
                });

            $userIds = $userQuery->pluck('user_id');

            if ($userIds->isEmpty()) {
                continue;
            }

            $settledUserIds = JobMatch::query()
                ->where('job_listing_id', $listing->id)
                ->whereIn('user_id', $userIds)
                ->whereIn('status', [JobMatchStatus::Dismissed, JobMatchStatus::Applied])
                ->pluck('user_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach ($userIds as $userId) {
                $userId = (int) $userId;

                if (in_array($userId, $settledUserIds, true)) {
                    continue;
                }

                if ($applicationOverlap->overlapsExistingApplication($userId, $listing)) {
                    continue;
                }

                EvaluateJobMatchJob::dispatch($userId, $listing->id);
            }
        }
    }
}
