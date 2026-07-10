<?php

namespace App\Services;

use App\Enums\JobAlertsTier;
use App\Enums\JobMatchStatus;
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
        protected JobListingDetailEnrichmentService $detailEnrichment,
    ) {}

    /**
     * @return array{evaluated: int, removed: int}
     */
    public function dispatchForUser(User $user, bool $force = false, ?int $recentPerSource = null): array
    {
        $tier = $user->jobAlertsTier();

        if ($tier === JobAlertsTier::None) {
            return ['evaluated' => 0, 'removed' => 0];
        }

        /** @var UserJobProfile|null $profile */
        $profile = $user->jobProfile;

        if (! $profile) {
            return ['evaluated' => 0, 'removed' => 0];
        }

        if ($tier === JobAlertsTier::Ai && trim((string) $profile->profile_text) === '') {
            return ['evaluated' => 0, 'removed' => 0];
        }

        if ($tier === JobAlertsTier::Regex && ! $this->patternMatcher->hasRules($profile->include_keywords, $profile->exclude_keywords)) {
            return ['evaluated' => 0, 'removed' => 0];
        }

        $removed = $this->removeExcludedMatches($user, $profile);

        $sourceIds = UserJobSourceSubscription::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('job_source_id');

        if ($sourceIds->isEmpty()) {
            return 0;
        }

        if ($recentPerSource !== null) {
            $listingIds = collect(
                app(JobMatchListingScopeService::class)->recentListingIdsForSources($sourceIds->all(), $recentPerSource)
            );
        } else {
            $listingIds = JobListing::query()
                ->whereIn('job_source_id', $sourceIds)
                ->pluck('id');
        }

        if ($force) {
            $existingMatchListingIds = JobMatch::query()
                ->where('user_id', $user->id)
                ->whereNot('status', JobMatchStatus::Dismissed)
                ->pluck('job_listing_id');

            $listingIds = $listingIds->merge($existingMatchListingIds)->unique()->values();
        }

        if ($listingIds->isEmpty()) {
            return ['evaluated' => 0, 'removed' => $removed];
        }

        $listings = JobListing::query()
            ->whereIn('id', $listingIds)
            ->get(['id', 'job_source_id', 'content_hash', 'title', 'url']);

        if ($listings->isEmpty()) {
            return ['evaluated' => 0, 'removed' => $removed];
        }

        $existingMatches = JobMatch::query()
            ->where('user_id', $user->id)
            ->get(['job_listing_id', 'evaluation_cache_key'])
            ->keyBy('job_listing_id');

        $evaluated = 0;

        foreach ($listings as $listing) {
            if ($this->applicationOverlap->overlapsExistingApplication($user->id, $listing)) {
                continue;
            }

            if ($this->shouldSkipExcludedTitle($tier, $profile, $listing, $user->id)) {
                continue;
            }

            if (! $force) {
                $cacheKey = $this->evaluationCacheKey($tier, $profile, $listing);
                $existing = $existingMatches->get($listing->id);

                if ($existing && $existing->evaluation_cache_key === $cacheKey) {
                    continue;
                }
            }

            $job = new EvaluateJobMatchJob($user->id, $listing->id, $force);

            $job->handle(
                $this->evaluator,
                $this->patternMatcher,
                $this->detailEnrichment,
                $this->applicationOverlap,
            );

            $evaluated++;
        }

        return ['evaluated' => $evaluated, 'removed' => $removed];
    }

    public function removeExcludedMatches(User $user, UserJobProfile $profile): int
    {
        if (! $this->patternMatcher->hasExcludeRules($profile->exclude_keywords)) {
            return 0;
        }

        $removed = 0;

        $matches = JobMatch::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [JobMatchStatus::PendingNotify, JobMatchStatus::Notified])
            ->with('jobListing:id,title')
            ->get();

        foreach ($matches as $match) {
            $title = (string) ($match->jobListing?->title ?? '');

            if ($title === '') {
                continue;
            }

            if ($this->patternMatcher->evaluateTitleExcludes($title, $profile->exclude_keywords) === null) {
                continue;
            }

            $match->delete();
            $removed++;
        }

        return $removed;
    }

    protected function shouldSkipExcludedTitle(
        JobAlertsTier $tier,
        UserJobProfile $profile,
        JobListing $listing,
        int $userId,
    ): bool {
        if ($tier !== JobAlertsTier::Ai) {
            return false;
        }

        if ($this->patternMatcher->evaluateTitleExcludes(
            (string) $listing->title,
            $profile->exclude_keywords,
        ) === null) {
            return false;
        }

        JobMatch::query()
            ->where('user_id', $userId)
            ->where('job_listing_id', $listing->id)
            ->delete();

        return true;
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
                $profile->exclude_keywords,
            ),
            JobAlertsTier::None => '',
        };
    }
}
