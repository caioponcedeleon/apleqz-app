<?php

namespace App\Jobs;

use App\Enums\JobAlertsTier;
use App\Enums\JobMatchStatus;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\User;
use App\Models\UserJobProfile;
use App\Services\JobListingDetailEnrichmentService;
use App\Services\JobMatchApplicationOverlapChecker;
use App\Services\JobMatchEvaluator;
use App\Services\JobTitlePatternMatcher;
use App\Support\AiUsageContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateJobMatchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public string $listingId,
        public bool $forceReevaluate = false,
    ) {}

    public function handle(
        JobMatchEvaluator $evaluator,
        JobTitlePatternMatcher $patternMatcher,
        JobListingDetailEnrichmentService $detailEnrichment,
        JobMatchApplicationOverlapChecker $applicationOverlap,
    ): void {
        $user = User::query()->find($this->userId);

        if (! $user || $user->jobAlertsTier() === JobAlertsTier::None) {
            return;
        }

        $listing = JobListing::query()->with('jobSource')->find($this->listingId);

        if (! $listing) {
            return;
        }

        if ($applicationOverlap->overlapsExistingApplication($user->id, $listing)) {
            return;
        }

        /** @var UserJobProfile|null $profile */
        $profile = $user->jobProfile;

        if (! $profile) {
            return;
        }

        $existing = JobMatch::query()
            ->where('user_id', $user->id)
            ->where('job_listing_id', $listing->id)
            ->first();

        // Once skipped (dismissed) or applied, never revive the listing as a new match.
        if ($existing && in_array($existing->status, [JobMatchStatus::Dismissed, JobMatchStatus::Applied], true)) {
            return;
        }

        $result = match ($user->jobAlertsTier()) {
            JobAlertsTier::Regex => $this->evaluateRegexMatch($profile, $listing, $patternMatcher),
            JobAlertsTier::Ai => $this->evaluateAiMatch($profile, $listing, $evaluator, $patternMatcher, $detailEnrichment, $existing),
            JobAlertsTier::None => null,
        };

        if (! is_array($result)) {
            return;
        }

        if ($result['fit_score'] < $profile->min_fit_score) {
            if ($existing) {
                $existing->delete();
            }

            return;
        }

        JobMatch::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'job_listing_id' => $listing->id,
            ],
            [
                'fit_score' => $result['fit_score'],
                'reason' => $result['reason'],
                'status' => $existing?->status === JobMatchStatus::Notified
                    ? JobMatchStatus::Notified
                    : JobMatchStatus::PendingNotify,
                'evaluation_cache_key' => $result['evaluation_cache_key'],
            ],
        );
    }

    /**
     * @return array{fit_score: int, reason: string, evaluation_cache_key: string}|null
     */
    protected function evaluateRegexMatch(
        UserJobProfile $profile,
        JobListing $listing,
        JobTitlePatternMatcher $patternMatcher,
    ): ?array {
        if (! $patternMatcher->hasRules($profile->include_keywords, $profile->exclude_keywords)) {
            return null;
        }

        $cacheKey = $patternMatcher->evaluationCacheKey(
            (string) $profile->include_keywords,
            (string) $profile->exclude_keywords,
            $listing->content_hash,
        );

        $existing = JobMatch::query()
            ->where('user_id', $profile->user_id)
            ->where('job_listing_id', $listing->id)
            ->first();

        if (! $this->forceReevaluate && $existing && $existing->evaluation_cache_key === $cacheKey) {
            return null;
        }

        $result = $patternMatcher->evaluate(
            (string) $listing->title,
            $profile->include_keywords,
            $profile->exclude_keywords,
        );

        return [
            'fit_score' => $result['fit_score'],
            'reason' => $result['reason'],
            'evaluation_cache_key' => $cacheKey,
        ];
    }

    /**
     * @return array{fit_score: int, reason: string, evaluation_cache_key: string}|null
     */
    protected function evaluateAiMatch(
        UserJobProfile $profile,
        JobListing $listing,
        JobMatchEvaluator $evaluator,
        JobTitlePatternMatcher $patternMatcher,
        JobListingDetailEnrichmentService $detailEnrichment,
        ?JobMatch $existing,
    ): ?array {
        if (trim($profile->profile_text) === '') {
            return null;
        }

        $cacheKey = $evaluator->evaluationCacheKey(
            $profile->profile_text,
            $listing->content_hash,
            $profile->exclude_keywords,
        );

        if (! $this->forceReevaluate && $existing && $existing->evaluation_cache_key === $cacheKey) {
            return null;
        }

        $excludeResult = $patternMatcher->evaluateTitleExcludes(
            (string) $listing->title,
            $profile->exclude_keywords,
        );

        if ($excludeResult !== null) {
            return [
                'fit_score' => $excludeResult['fit_score'],
                'reason' => $excludeResult['reason'],
                'evaluation_cache_key' => $cacheKey,
            ];
        }

        $result = AiUsageContext::run(
            ['user_id' => $profile->user_id, 'purpose' => 'job_match'],
            fn (): array => $evaluator->evaluate($profile->profile_text, $listing),
        );

        $detailConfig = $listing->jobSource
            ? $detailEnrichment->detailConfigFor($listing->jobSource)
            : null;
        $fetchMinScore = is_array($detailConfig)
            ? (int) ($detailConfig['fetch_min_score'] ?? config('job_match.detail_fetch_min_score', 60))
            : (int) config('job_match.detail_fetch_min_score', 60);
        $canEnrich = $detailConfig !== null
            && $listing->detail_enriched_at === null
            && is_string($listing->url)
            && trim($listing->url) !== '';
        $needsDetail = $canEnrich && $result['fit_score'] >= $fetchMinScore;

        if ($needsDetail) {
            EnrichJobListingDetailJob::dispatch($listing->id);

            // Defer saving only for async scrape flow. Force rematch must still
            // surface good matches immediately; enrichment can refine them later.
            if (! $this->forceReevaluate && $result['fit_score'] >= $profile->min_fit_score) {
                return null;
            }
        }

        return [
            'fit_score' => $result['fit_score'],
            'reason' => $result['reason'],
            'evaluation_cache_key' => $cacheKey,
        ];
    }
}
