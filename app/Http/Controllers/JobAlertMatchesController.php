<?php

namespace App\Http\Controllers;

use App\Enums\JobMatchStatus;
use App\Models\JobMatch;
use App\Services\JobListingDetailEnrichmentService;
use App\Services\JobMatchApplicationService;
use App\Services\JobMatchRematchService;
use App\Services\JobSourcePreviewService;
use Illuminate\Http\JsonResponse;
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
            ->whereIn('status', [JobMatchStatus::PendingNotify, JobMatchStatus::Notified])
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
            'canRunMatches' => (bool) $request->user()->is_admin,
        ]);
    }

    public function runMatches(Request $request, JobMatchRematchService $rematch): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $dispatched = $rematch->dispatchForUser($request->user(), force: true);

        if ($dispatched === 0) {
            return redirect()
                ->route('job-alerts.matches')
                ->with('warning', __('app.job_alerts.run_matches_none'));
        }

        return redirect()
            ->route('job-alerts.matches')
            ->with('success', __('app.job_alerts.run_matches_dispatched', ['count' => $dispatched]));
    }

    public function preview(
        Request $request,
        JobMatch $jobMatch,
        JobSourcePreviewService $preview,
        JobListingDetailEnrichmentService $detailEnrichment,
    ): JsonResponse {
        abort_unless($jobMatch->user_id === $request->user()->id, 403);

        $jobMatch->load('jobListing.jobSource');
        $listing = $jobMatch->jobListing;

        if (! $listing || ! is_string($listing->url) || trim($listing->url) === '') {
            return response()->json([
                'message' => __('app.job_alerts.preview_unavailable'),
            ], 422);
        }

        $validated = $request->validate([
            'engine' => ['nullable', 'string', 'in:http,playwright'],
        ]);

        $options = $detailEnrichment->previewOptionsFor($listing);
        $engine = $validated['engine'] ?? $options['engine'];

        $result = $preview->prepare($listing->url, [
            'engine' => $engine,
            'interactions' => $options['interactions'],
            'inject_picker' => false,
        ]);

        return response()->json([
            'html' => $result['html'],
            'rendered_with' => $result['rendered_with'],
            'suggest_playwright' => $result['suggest_playwright'],
            'url' => $listing->url,
            'title' => $listing->title,
        ]);
    }

    public function dismiss(Request $request, JobMatch $jobMatch): RedirectResponse|JsonResponse
    {
        abort_unless($jobMatch->user_id === $request->user()->id, 403);

        $jobMatch->update([
            'status' => JobMatchStatus::Dismissed,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('app.job_alerts.match_dismissed'),
            ]);
        }

        return redirect()
            ->route('job-alerts.matches')
            ->with('success', __('app.job_alerts.match_dismissed'));
    }

    public function saveForLater(Request $request, JobMatch $jobMatch, JobMatchApplicationService $applications): JsonResponse
    {
        $application = $applications->saveForLater($request->user(), $jobMatch);

        return response()->json([
            'message' => __('app.job_alerts.save_for_later_success'),
            'application_id' => $application->id,
            'application_uuid' => $application->uuid,
            'edit_url' => route('applications.edit', $application),
        ]);
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
