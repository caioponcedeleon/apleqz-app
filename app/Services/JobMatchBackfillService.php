<?php

namespace App\Services;

use App\Jobs\EvaluateJobMatchJob;
use App\Models\AiUsageRecord;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\UserJobProfile;
use App\Models\UserJobSourceSubscription;
use Illuminate\Support\Collection;

class JobMatchBackfillService
{
    public function __construct(
        protected JobMatchEvaluator $evaluator,
        protected AiUsageRecorder $usageRecorder,
        protected JobMatchApplicationOverlapChecker $applicationOverlap,
    ) {}

    /**
     * @return array{
     *     evaluations: int,
     *     listings: int,
     *     users: int,
     *     skipped_cached: int,
     *     skipped_no_profile: int,
     *     estimated_prompt_tokens: int,
     *     estimated_completion_tokens: int,
     *     estimated_total_tokens: int,
     *     estimated_cost_eur: float|null,
     *     estimated_seconds: int,
     *     pricing_available: bool,
     *     uses_historical_averages: bool
     * }
     */
    public function estimate(?string $driver = null, ?string $model = null): array
    {
        $scan = $this->scan();

        [$avgPrompt, $avgCompletion, $usesHistorical] = $this->averageTokensPerEvaluation($driver, $model);

        $estimatedPrompt = $scan['evaluations'] * $avgPrompt;
        $estimatedCompletion = $scan['evaluations'] * $avgCompletion;

        return [
            'evaluations' => $scan['evaluations'],
            'listings' => $scan['listings'],
            'users' => $scan['users'],
            'skipped_cached' => $scan['skipped_cached'],
            'skipped_no_profile' => $scan['skipped_no_profile'],
            'estimated_prompt_tokens' => $estimatedPrompt,
            'estimated_completion_tokens' => $estimatedCompletion,
            'estimated_total_tokens' => $estimatedPrompt + $estimatedCompletion,
            'estimated_cost_eur' => $this->usageRecorder->estimateCostEur($model, $estimatedPrompt, $estimatedCompletion),
            'estimated_seconds' => $scan['evaluations'] * (int) config('job_match.estimates.seconds_per_evaluation', 3),
            'pricing_available' => is_array(config("job_match.pricing.{$model}")),
            'uses_historical_averages' => $usesHistorical,
        ];
    }

    public function dispatchPending(): int
    {
        $scan = $this->scan();

        foreach ($scan['pairs'] as $pair) {
            EvaluateJobMatchJob::dispatch($pair['user_id'], $pair['listing_id']);
        }

        return $scan['evaluations'];
    }

    /**
     * @return array{
     *     evaluations: int,
     *     listings: int,
     *     users: int,
     *     skipped_cached: int,
     *     skipped_no_profile: int,
     *     pairs: list<array{user_id: int, listing_id: string}>
     * }
     */
    protected function scan(): array
    {
        $subscriptions = UserJobSourceSubscription::query()
            ->where('is_active', true)
            ->get(['user_id', 'job_source_id']);

        if ($subscriptions->isEmpty()) {
            return $this->emptyScan();
        }

        $profiles = UserJobProfile::query()
            ->whereNotNull('profile_text')
            ->where('profile_text', '!=', '')
            ->get(['user_id', 'profile_text'])
            ->keyBy('user_id');

        $subscribersBySource = $subscriptions->groupBy('job_source_id');
        $listings = JobListing::query()->get(['id', 'job_source_id', 'content_hash', 'title', 'url']);

        if ($listings->isEmpty()) {
            return $this->emptyScan();
        }

        $existingMatches = JobMatch::query()
            ->get(['user_id', 'job_listing_id', 'evaluation_cache_key'])
            ->keyBy(fn (JobMatch $match): string => $match->user_id.'|'.$match->job_listing_id);

        $pairs = [];
        $listingIds = [];
        $userIds = [];
        $skippedCached = 0;
        $skippedNoProfile = 0;

        foreach ($listings as $listing) {
            /** @var Collection<int, UserJobSourceSubscription> $sourceSubscriptions */
            $sourceSubscriptions = $subscribersBySource->get($listing->job_source_id, collect());

            foreach ($sourceSubscriptions->pluck('user_id')->unique() as $userId) {
                $profile = $profiles->get($userId);

                if (! $profile) {
                    $skippedNoProfile++;

                    continue;
                }

                $cacheKey = $this->evaluator->evaluationCacheKey($profile->profile_text, $listing->content_hash);
                $existing = $existingMatches->get($userId.'|'.$listing->id);

                if ($existing && $existing->evaluation_cache_key === $cacheKey) {
                    $skippedCached++;

                    continue;
                }

                if ($this->applicationOverlap->overlapsExistingApplication((int) $userId, $listing)) {
                    continue;
                }

                $pairs[] = [
                    'user_id' => (int) $userId,
                    'listing_id' => $listing->id,
                ];
                $listingIds[$listing->id] = true;
                $userIds[$userId] = true;
            }
        }

        return [
            'evaluations' => count($pairs),
            'listings' => count($listingIds),
            'users' => count($userIds),
            'skipped_cached' => $skippedCached,
            'skipped_no_profile' => $skippedNoProfile,
            'pairs' => $pairs,
        ];
    }

    /**
     * @return array{
     *     evaluations: int,
     *     listings: int,
     *     users: int,
     *     skipped_cached: int,
     *     skipped_no_profile: int,
     *     pairs: list<array{user_id: int, listing_id: string}>
     * }
     */
    protected function emptyScan(): array
    {
        return [
            'evaluations' => 0,
            'listings' => 0,
            'users' => 0,
            'skipped_cached' => 0,
            'skipped_no_profile' => 0,
            'pairs' => [],
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: bool}
     */
    protected function averageTokensPerEvaluation(?string $driver, ?string $model): array
    {
        $query = AiUsageRecord::query()->where('purpose', 'job_match');

        if ($driver !== null) {
            $query->where('driver', $driver);
        }

        if ($model !== null) {
            $query->where('model', $model);
        }

        if ((clone $query)->count() >= 3) {
            return [
                max(1, (int) round((float) (clone $query)->avg('prompt_tokens'))),
                max(1, (int) round((float) (clone $query)->avg('completion_tokens'))),
                true,
            ];
        }

        return [
            (int) config('job_match.estimates.default_prompt_tokens', 600),
            (int) config('job_match.estimates.default_completion_tokens', 40),
            false,
        ];
    }
}
