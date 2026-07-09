<?php

namespace App\Http\Controllers;

use App\Enums\JobMatchStatus;
use App\Models\JobMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobAlertMatchesController extends Controller
{
    public function index(Request $request): Response
    {
        $matches = JobMatch::query()
            ->where('user_id', $request->user()->id)
            ->whereNot('status', JobMatchStatus::Dismissed)
            ->with(['jobListing:id,title,url,company,location'])
            ->orderByDesc('fit_score')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (JobMatch $match): array => [
                'id' => $match->id,
                'fit_score' => $match->fit_score,
                'reason' => $match->reason,
                'status' => $match->status->value,
                'created_at' => $match->created_at?->toIso8601String(),
                'listing' => [
                    'id' => $match->jobListing?->id,
                    'title' => $match->jobListing?->title,
                    'url' => $match->jobListing?->url,
                    'company' => $match->jobListing?->company,
                    'location' => $match->jobListing?->location,
                ],
            ]);

        return Inertia::render('JobAlerts/Matches', [
            'matches' => $matches,
            'canCreateApplication' => $request->user()->areas()->exists()
                && $request->user()->applicationWaves()->exists(),
        ]);
    }

    public function dismiss(Request $request, JobMatch $jobMatch): RedirectResponse
    {
        abort_unless($jobMatch->user_id === $request->user()->id, 403);

        $jobMatch->update([
            'status' => JobMatchStatus::Dismissed,
        ]);

        return redirect()
            ->route('job-alerts.matches')
            ->with('success', __('app.job_alerts.match_dismissed'));
    }

    public function apply(Request $request, JobMatch $jobMatch): RedirectResponse
    {
        abort_unless($jobMatch->user_id === $request->user()->id, 403);

        $jobMatch->load('jobListing:id,title,url,company,location');
        $listing = $jobMatch->jobListing;

        return redirect()->route('applications.create', array_filter([
            'position' => $listing?->title,
            'company' => $listing?->company,
            'location' => $listing?->location,
            'job_url' => $listing?->url,
            'job_match_id' => $jobMatch->id,
        ], fn (?string $value): bool => is_string($value) && $value !== ''));
    }
}
