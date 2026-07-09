<?php

namespace App\Jobs;

use App\Enums\JobMatchStatus;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\User;
use App\Models\UserJobProfile;
use App\Services\JobMatchEvaluator;
use App\Support\AiUsageContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateJobMatchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public string $listingId,
    ) {}

    public function handle(JobMatchEvaluator $evaluator): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $listing = JobListing::query()->find($this->listingId);

        if (! $listing) {
            return;
        }

        /** @var UserJobProfile|null $profile */
        $profile = $user->jobProfile;

        if (! $profile || trim($profile->profile_text) === '') {
            return;
        }

        $cacheKey = $evaluator->evaluationCacheKey($profile->profile_text, $listing->content_hash);

        $existing = JobMatch::query()
            ->where('user_id', $user->id)
            ->where('job_listing_id', $listing->id)
            ->first();

        if ($existing && $existing->evaluation_cache_key === $cacheKey) {
            return;
        }

        $result = AiUsageContext::run(
            ['user_id' => $user->id, 'purpose' => 'job_match'],
            fn (): array => $evaluator->evaluate($profile->profile_text, $listing),
        );

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
                'evaluation_cache_key' => $cacheKey,
            ],
        );
    }
}
