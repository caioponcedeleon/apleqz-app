<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobAlertSettingsRequest;
use App\Models\JobSource;
use App\Models\UserJobProfile;
use App\Models\UserJobSourceSubscription;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobAlertSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->jobProfile;

        $subscribedSourceIds = $user->jobSourceSubscriptions()
            ->where('is_active', true)
            ->pluck('job_source_id')
            ->all();

        return Inertia::render('JobAlerts/Settings', [
            'profile' => [
                'profile_text' => $profile?->profile_text ?? '',
                'min_fit_score' => $profile?->min_fit_score ?? UserJobProfile::DEFAULT_MIN_FIT_SCORE,
                'job_alerts_enabled' => (bool) ($profile?->job_alerts_enabled ?? false),
            ],
            'sources' => JobSource::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'company_name']),
            'subscribedSourceIds' => $subscribedSourceIds,
            'emailVerified' => $user->hasVerifiedEmail(),
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
        ]);
    }

    public function update(JobAlertSettingsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->jobProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'profile_text' => $validated['profile_text'] ?? '',
                'min_fit_score' => $validated['min_fit_score'],
                'job_alerts_enabled' => (bool) ($validated['job_alerts_enabled'] ?? false),
            ],
        );

        $this->syncSubscriptions($user, $validated['subscribed_source_ids'] ?? []);

        return redirect()
            ->route('job-alerts.settings')
            ->with('success', __('app.job_alerts.saved'));
    }

    /**
     * @param  list<string>  $subscribedSourceIds
     */
    protected function syncSubscriptions(\App\Models\User $user, array $subscribedSourceIds): void
    {
        $subscribedSourceIds = collect($subscribedSourceIds)->unique()->values();
        $activeSourceIds = JobSource::query()
            ->where('is_active', true)
            ->pluck('id');

        foreach ($activeSourceIds as $sourceId) {
            $isActive = $subscribedSourceIds->contains($sourceId);

            if ($isActive) {
                UserJobSourceSubscription::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'job_source_id' => $sourceId,
                    ],
                    ['is_active' => true],
                );

                continue;
            }

            UserJobSourceSubscription::query()
                ->where('user_id', $user->id)
                ->where('job_source_id', $sourceId)
                ->update(['is_active' => false]);
        }
    }
}
