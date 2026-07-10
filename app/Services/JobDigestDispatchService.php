<?php

namespace App\Services;

use App\Enums\JobAlertsTier;
use App\Enums\JobMatchStatus;
use App\Models\JobMatch;
use App\Models\User;
use App\Notifications\JobMatchesDigestNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class JobDigestDispatchService
{
    /**
     * @return list<int>
     */
    public function sendPendingDigests(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $sentUserIds = [];

        $userIds = JobMatch::query()
            ->where('status', JobMatchStatus::PendingNotify)
            ->whereHas('user', function ($query): void {
                $query->whereNotNull('email_verified_at')
                    ->where('job_alerts_tier', '!=', JobAlertsTier::None->value);
            })
            ->whereHas('user.jobProfile', function ($query): void {
                $query->where('job_alerts_enabled', true);
            })
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);

            if (! $user) {
                continue;
            }

            $matches = $this->pendingMatchesForUser($userId, $this->maxMatchesPerEmail());

            if ($matches->isEmpty()) {
                continue;
            }

            $user->notify(new JobMatchesDigestNotification($matches));
            $this->markNotified($matches, $now);
            $sentUserIds[] = $userId;
        }

        return $sentUserIds;
    }

    /**
     * @return Collection<int, JobMatch>
     */
    protected function maxMatchesPerEmail(): int
    {
        return max(1, (int) config('job_match.digest.max_per_email', 10));
    }

    protected function pendingMatchesForUser(int $userId, int $limit): Collection
    {
        return JobMatch::query()
            ->where('user_id', $userId)
            ->where('status', JobMatchStatus::PendingNotify)
            ->with(['jobListing:id,title,url,company,location'])
            ->orderByDesc('fit_score')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Collection<int, JobMatch>  $matches
     */
    protected function markNotified(Collection $matches, Carbon $now): void
    {
        JobMatch::query()
            ->whereIn('id', $matches->pluck('id'))
            ->update([
                'status' => JobMatchStatus::Notified,
                'notified_at' => $now,
            ]);
    }
}
