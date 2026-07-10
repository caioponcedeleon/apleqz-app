<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\JobMatchStatus;
use App\Models\Application;
use App\Models\JobMatch;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class JobMatchApplicationService
{
    public function __construct(
        protected ApplicationStatusHistoryService $statusHistory,
    ) {}

    public function saveForLater(User $user, JobMatch $jobMatch): Application
    {
        abort_unless($jobMatch->user_id === $user->id, 403);

        $jobMatch->load('jobListing.jobSource');
        $listing = $jobMatch->jobListing;

        if (! $listing) {
            throw ValidationException::withMessages([
                'job_match' => __('app.job_alerts.save_for_later_unavailable'),
            ]);
        }

        $area = $user->areas()->orderBy('name')->first();
        $wave = $user->currentWave
            ?? $user->applicationWaves()->where('is_default', true)->first()
            ?? $user->applicationWaves()->orderBy('name')->first();

        if (! $area || ! $wave) {
            throw ValidationException::withMessages([
                'application' => __('app.job_alerts.save_for_later_needs_setup'),
            ]);
        }

        $company = trim((string) ($listing->company ?? ''));
        if ($company === '') {
            $company = trim((string) ($listing->jobSource?->company_name ?? ''));
        }
        if ($company === '') {
            $company = __('app.job_alerts.unknown_company');
        }

        $notes = trim($jobMatch->reason);
        $notes = $notes !== '' ? $notes : null;

        $application = $user->applications()->create([
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
            'position' => $listing->title,
            'company' => $company,
            'location' => $listing->location,
            'status' => ApplicationStatus::WaitingToApply,
            'job_url' => $listing->url,
            'notes' => $notes,
            'applied_at' => null,
        ]);

        $this->statusHistory->recordInitial($application);

        $jobMatch->update([
            'status' => JobMatchStatus::Applied,
        ]);

        return $application;
    }
}
