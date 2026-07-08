<?php

namespace App\Services;

use App\Enums\ApplicationMomentType;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Collection;

class ApplicationStatisticsService
{
    public function forUser(User $user, ?string $waveId = null): array
    {
        $query = Application::query()
            ->where('user_id', $user->id)
            ->with(['area', 'moments']);

        if ($waveId) {
            $query->where('application_wave_id', $waveId);
        }

        $applications = $query->get();

        return [
            'summary' => $this->summary($applications),
            'by_area' => $this->byArea($applications),
            'application_timeline' => $this->applicationTimeline($applications),
            'interview_timeline' => $this->interviewTimeline($applications),
        ];
    }

    protected function summary(Collection $applications): array
    {
        $total = $applications->count();
        $rejections = $applications->where('status', ApplicationStatus::Rejected)->count();
        $interviews = $applications->filter(fn (Application $a) => $a->hasInterview())->count();
        $offers = $applications->where('status', ApplicationStatus::Offer)->count();
        $waiting = $applications->where('status', ApplicationStatus::Waiting)->count();
        $waitingToApply = $applications->where('status', ApplicationStatus::WaitingToApply)->count();
        $declinedByMe = $applications->where('status', ApplicationStatus::DeclinedByMe)->count();

        $rejectionDays = $applications
            ->filter(fn (Application $a) => $a->days_after_rejection !== null)
            ->pluck('days_after_rejection');

        $avgDaysToRejection = $rejectionDays->isNotEmpty()
            ? round($rejectionDays->avg(), 1)
            : null;

        $dailyCounts = $applications
            ->filter(fn (Application $a) => $a->applied_at !== null)
            ->groupBy(fn (Application $a) => $a->applied_at->toDateString())
            ->map->count();

        $avgApplicationsPerDay = $dailyCounts->isNotEmpty()
            ? round($dailyCounts->avg(), 2)
            : null;

        return [
            'total_applications' => $total,
            'total_rejections' => $rejections,
            'total_interviews' => $interviews,
            'total_offers' => $offers,
            'total_waiting' => $waiting,
            'total_waiting_to_apply' => $waitingToApply,
            'total_declined_by_me' => $declinedByMe,
            'avg_days_to_rejection' => $avgDaysToRejection,
            'avg_applications_per_day' => $avgApplicationsPerDay,
        ];
    }

    protected function byArea(Collection $applications): array
    {
        return $applications
            ->groupBy('area_id')
            ->map(function (Collection $group) {
                $total = $group->count();
                $rejections = $group->where('status', ApplicationStatus::Rejected)->count();
                $interviews = $group->filter(fn (Application $a) => $a->hasInterview())->count();
                $waiting = $group->where('status', ApplicationStatus::Waiting)->count();
                $waitingToApply = $group->where('status', ApplicationStatus::WaitingToApply)->count();
                $declinedByMe = $group->where('status', ApplicationStatus::DeclinedByMe)->count();
                $withdrawn = $group->where('status', ApplicationStatus::Withdrawn)->count();
                $cancelled = $group->where('status', ApplicationStatus::Cancelled)->count();
                $offers = $group->where('status', ApplicationStatus::Offer)->count();

                $pct = fn (int $count) => $total > 0 ? round($count / $total, 4) : 0;

                $area = $group->first()->area;

                return [
                    'area_id' => $area->id,
                    'area_name' => $area->name,
                    'applied' => $total,
                    'rejections' => $rejections,
                    'interviews' => $interviews,
                    'waiting' => $waiting,
                    'waiting_to_apply' => $waitingToApply,
                    'declined_by_me' => $declinedByMe,
                    'withdrawn' => $withdrawn,
                    'cancelled' => $cancelled,
                    'offers' => $offers,
                    'pct_rejections' => $pct($rejections),
                    'pct_interviews' => $pct($interviews),
                    'pct_interviews_per_application' => $pct($interviews),
                    'pct_waiting' => $pct($waiting),
                    'pct_declined_by_me' => $pct($declinedByMe),
                    'pct_offers' => $pct($offers),
                ];
            })
            ->values()
            ->sortBy('area_name')
            ->values()
            ->all();
    }

    protected function applicationTimeline(Collection $applications): array
    {
        $dates = $applications
            ->filter(fn (Application $a) => $a->applied_at !== null)
            ->pluck('applied_at')
            ->map(fn ($d) => $d->toDateString())
            ->unique()
            ->sort()
            ->values();

        $cumulative = 0;
        $previousCumulative = 0;

        return $dates->map(function (string $date) use ($applications, &$cumulative, &$previousCumulative) {
            $cumulative = $applications->filter(
                fn (Application $a) => $a->applied_at !== null && $a->applied_at->toDateString() <= $date
            )->count();

            $daily = $cumulative - $previousCumulative;
            $previousCumulative = $cumulative;

            return [
                'date' => $date,
                'cumulative' => $cumulative,
                'daily' => $daily,
            ];
        })->all();
    }

    protected function interviewTimeline(Collection $applications): array
    {
        $interviewDates = $applications
            ->flatMap(function (Application $application) {
                return $application->moments
                    ->filter(fn ($moment) => $moment->type === ApplicationMomentType::Interview)
                    ->pluck('occurred_at');
            })
            ->map(fn ($date) => $date->toDateString())
            ->unique()
            ->sort()
            ->values();

        return $interviewDates->map(function (string $date) use ($applications) {
            $cumulative = $applications->sum(
                fn (Application $application) => $application->moments
                    ->filter(
                        fn ($moment) => $moment->type === ApplicationMomentType::Interview
                            && $moment->occurred_at->toDateString() <= $date
                    )
                    ->count()
            );

            return [
                'date' => $date,
                'cumulative' => $cumulative,
            ];
        })->all();
    }
}
