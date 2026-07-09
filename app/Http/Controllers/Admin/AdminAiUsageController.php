<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendJobDigestsAfterMatchRunJob;
use App\Models\AiUsageRecord;
use App\Services\AiUsageRecorder;
use App\Services\JobMatchBackfillService;
use App\Services\JobMatchRunTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminAiUsageController extends Controller
{
    public function index(AiUsageRecorder $recorder): Response
    {
        $driver = config('job_match.driver');
        $model = $driver === 'ollama'
            ? config('job_match.ollama.model')
            : config('job_match.mistral.model');

        $summary = $recorder->summarize($driver, $model);
        $allTime = $recorder->summarize();

        $recent = AiUsageRecord::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (AiUsageRecord $record): array => [
                'id' => $record->id,
                'driver' => $record->driver,
                'model' => $record->model,
                'purpose' => $record->purpose,
                'prompt_tokens' => $record->prompt_tokens,
                'completion_tokens' => $record->completion_tokens,
                'total_tokens' => $record->total_tokens,
                'created_at' => $record->created_at?->toIso8601String(),
                'user' => $record->user?->only(['id', 'name', 'email']),
            ]);

        return Inertia::render('Admin/AiUsage', [
            'driver' => $driver,
            'model' => $model,
            'summary' => $summary,
            'allTime' => $allTime,
            'recent' => $recent,
            'pricingAvailable' => is_array(config("job_match.pricing.{$model}")),
            'queuedMatchJobs' => $this->queuedMatchJobsCount(),
            'queueConnection' => (string) config('queue.default'),
        ]);
    }

    protected function queuedMatchJobsCount(): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        $table = (string) config('queue.connections.database.table', 'jobs');

        return (int) DB::table($table)
            ->where('payload', 'like', '%EvaluateJobMatchJob%')
            ->count();
    }

    public function matchPreview(JobMatchBackfillService $backfill): JsonResponse
    {
        $driver = config('job_match.driver');
        $model = $driver === 'ollama'
            ? config('job_match.ollama.model')
            : config('job_match.mistral.model');

        return response()->json($backfill->estimate($driver, $model));
    }

    public function matchStatus(Request $request, JobMatchRunTracker $tracker): JsonResponse
    {
        $runId = $request->query('run');

        if (! is_string($runId) || $runId === '') {
            return response()->json(['found' => false], 404);
        }

        $status = $tracker->status($runId);

        if (! ($status['found'] ?? false)) {
            return response()->json(['found' => false], 404);
        }

        return response()->json($status);
    }

    public function runMatches(Request $request, JobMatchBackfillService $backfill, JobMatchRunTracker $tracker): RedirectResponse|JsonResponse
    {
        $dispatched = $backfill->dispatchPending();

        if ($dispatched === 0) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('app.administration.ai_usage_run_matches_none'),
                ], 422);
            }

            return redirect()
                ->route('administration.ai-usage')
                ->with('warning', __('app.administration.ai_usage_run_matches_none'));
        }

        $runId = $tracker->start($dispatched);

        SendJobDigestsAfterMatchRunJob::dispatch();

        if ($request->expectsJson()) {
            return response()->json([
                'run_id' => $runId,
                'total' => $dispatched,
            ]);
        }

        return redirect()
            ->route('administration.ai-usage')
            ->with('success', __('app.administration.ai_usage_run_matches_dispatched', [
                'count' => $dispatched,
            ]))
            ->with('match_run_id', $runId);
    }
}
