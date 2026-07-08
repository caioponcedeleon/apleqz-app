<?php

namespace App\Services;

use App\Enums\ApplicationMomentType;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Support\Carbon;

class ApplicationStatusHistoryService
{
    public function recordInitial(Application $application): void
    {
        $this->record($application, $application->status, $this->resolveOccurredAt($application));
    }

    public function recordIfChanged(Application $application, ApplicationStatus $previousStatus): void
    {
        if ($previousStatus === $application->status) {
            return;
        }

        $this->record($application, $application->status, now()->toDateString());
    }

    protected function record(Application $application, ApplicationStatus $status, string $date): void
    {
        $alreadyRecorded = $application->moments()
            ->where('is_system', true)
            ->where('type', ApplicationMomentType::StatusChange)
            ->where('notes', $status->value)
            ->whereDate('occurred_at', $date)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $nextSortOrder = ((int) $application->moments()->max('sort_order')) + 1;

        $application->moments()->create([
            'type' => ApplicationMomentType::StatusChange,
            'occurred_at' => $date,
            'notes' => $status->value,
            'sort_order' => $nextSortOrder,
            'is_system' => true,
        ]);
    }

    protected function resolveOccurredAt(Application $application): string
    {
        if ($application->applied_at instanceof Carbon) {
            return $application->applied_at->toDateString();
        }

        return now()->toDateString();
    }
}
