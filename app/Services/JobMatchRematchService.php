<?php

namespace App\Services;

use App\Enums\JobAlertsTier;
use App\Jobs\EvaluateJobMatchJob;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\User;
use App\Models\UserJobProfile;
use App\Models\UserJobSourceSubscription;

class JobMatchRematchService
{
    public function __construct(
        protected JobMatchEvaluator $evaluator,
        protected JobTitlePatternMatcher $patternMatcher,
        protected JobMatchApplicationOverlapChecker $applicationOverlap,
    ) {}

    public function dispatchForUser(User $user): int
    {
        $tier = $user->jobAlertsTier();

        if ($tier === JobAlertsTier::None) {
            return 0;
        }

        /** @var UserJobProfile|null $profile */
        $profile = $user->jobProfile;

        if (! $profile) {
            return 0;
        }

        if ($tier === JobAlertsTier::Ai && trim((string) $profile->profile_text) === '') {
            return 0;
        }

        if ($tier === JobAlertsTier::Regex && ! $this->patternMatcher->hasRules($profile->include_keywords, $profile->exclude_keywords)) {
            return 0;
        }

        $sourceIds = UserJobSourceSubscription::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('job_source_id');

        if ($sourceIds->isEmpty()) {
            return 0;
        }

        $listings = JobListing::query()
            ->whereIn('job_source_id', $sourceIds)
            ->get(['id', 'job_source_id', 'content_hash', 'title', 'url']);

        if ($listings->isEmpty()) {
            return 0;
        }

        $existingMatches = JobMatch::query()
            ->where('user_id', $user->id)
            ->get(['job_listing_id', 'evaluation_cache_key'])
            ->keyBy('job_listing_id');

        $dispatched = 0;

        foreach ($listings as $listing) {
            if ($this->applicationOverlap->overlapsExistingApplication($user->id, $listing)) {
                continue;
            }

            $cacheKey = $this->evaluationCacheKey($tier, $profile, $listing);
            $existing = $existingMatches->get($listing->id);

            if ($existing && $existing->evaluation_cache_key === $cacheKey) {
                continue;
            }

            EvaluateJobMatchJob::dispatch($user->id, $listing->id);
            $dispatched++;
        }

        return $dispatched;
    }

    protected function evaluationCacheKey(JobAlertsTier $tier, UserJobProfile $profile, JobListing $listing): string
    {
        return match ($tier) {
            JobAlertsTier::Regex => $this->patternMatcher->evaluationCacheKey(
                (string) $profile->include_keywords,
                (string) $profile->exclude_keywords,
                $listing->content_hash,
            ),
            JobAlertsTier::Ai => $this->evaluator->evaluationCacheKey(
                (string) $profile->profile_text,
                $listing->content_hash,
            ),
            JobAlertsTier::None => '',
        };
    }
}
