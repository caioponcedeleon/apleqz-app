<?php

namespace App\Http\Controllers;

use App\Enums\JobAlertsTier;
use App\Http\Requests\JobAlertSettingsRequest;
use App\Models\JobSource;
use App\Models\UserJobProfile;
use App\Models\UserJobSourceSubscription;
use App\Services\JobMatchListingScopeService;
use App\Services\JobMatchRematchService;
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
        $tier = $user->jobAlertsTier();

        $subscribedSourceIds = $user->jobSourceSubscriptions()
            ->where('is_active', true)
            ->pluck('job_source_id')
            ->all();

        return Inertia::render('JobAlerts/Settings', [
            'tier' => $tier->value,
            'profile' => [
                'profile_text' => $profile?->profile_text ?? '',
                'include_keywords' => $profile?->include_keywords ?? '',
                'exclude_keywords' => $profile?->exclude_keywords ?? '',
                'min_fit_score' => $profile?->min_fit_score ?? UserJobProfile::DEFAULT_MIN_FIT_SCORE,
                'job_alerts_enabled' => (bool) ($profile?->job_alerts_enabled ?? false),
            ],
            'sources' => JobSource::query()
                ->where('is_active', true)
                ->orderBy('company_name')
                ->orderBy('name')
                ->get(['id', 'name', 'company_name']),
            'subscribedSourceIds' => $subscribedSourceIds,
            'emailVerified' => $user->hasVerifiedEmail(),
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'profileTextMaxLength' => UserJobProfile::PROFILE_TEXT_MAX_LENGTH,
            'isAiTier' => $tier === JobAlertsTier::Ai,
            'isRegexTier' => $tier === JobAlertsTier::Regex,
        ]);
    }

    public function update(JobAlertSettingsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $tier = $user->jobAlertsTier();
        $profile = $user->jobProfile;

        $keywordsChanged = $tier === JobAlertsTier::Regex && (
            ($validated['include_keywords'] ?? '') !== (string) ($profile?->include_keywords ?? '')
            || ($validated['exclude_keywords'] ?? '') !== (string) ($profile?->exclude_keywords ?? '')
        );

        $profileData = [
            'min_fit_score' => $validated['min_fit_score'],
            'job_alerts_enabled' => (bool) ($validated['job_alerts_enabled'] ?? false),
        ];

        if ($tier === JobAlertsTier::Ai) {
            $profileData['profile_text'] = $validated['profile_text'] ?? '';
        }

        if ($tier === JobAlertsTier::Regex) {
            $profileData['include_keywords'] = $validated['include_keywords'] ?? '';
            $profileData['exclude_keywords'] = $validated['exclude_keywords'] ?? '';
        }

        $user->jobProfile()->updateOrCreate(
            ['user_id' => $user->id],
            $profileData,
        );

        $this->syncSubscriptions($user, $validated['subscribed_source_ids'] ?? []);

        if ($keywordsChanged) {
            app(JobMatchRematchService::class)->dispatchForUser(
                $user,
                force: true,
                recentPerSource: app(JobMatchListingScopeService::class)->recentPerSourceLimit(),
            );
        }

        if ($request->boolean('autosave')) {
            return back();
        }

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
