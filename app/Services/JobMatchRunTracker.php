<?php

namespace App\Services;

use App\Models\AiUsageRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JobMatchRunTracker
{
    protected const CACHE_TTL_MINUTES = 120;

    public function start(int $total): string
    {
        $runId = (string) Str::uuid();

        Cache::put($this->cacheKey($runId), [
            'total' => $total,
            'started_at' => now()->toIso8601String(),
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $runId;
    }

    /**
     * @return array{
     *     found: bool,
     *     run_id?: string,
     *     total?: int,
     *     completed?: int,
     *     failed?: int,
     *     queued?: int,
     *     processed?: int,
     *     finished?: bool,
     *     queue_connection?: string,
     *     worker_needed?: bool
     * }
     */
    public function status(string $runId): array
    {
        $run = Cache::get($this->cacheKey($runId));

        if (! is_array($run)) {
            return ['found' => false];
        }

        $startedAt = is_string($run['started_at'] ?? null) ? $run['started_at'] : now()->toIso8601String();
        $total = is_int($run['total'] ?? null) ? $run['total'] : (int) ($run['total'] ?? 0);
        $completed = AiUsageRecord::query()
            ->where('purpose', 'job_match')
            ->where('created_at', '>=', $startedAt)
            ->count();
        $failed = $this->countFailedMatchJobs($startedAt);
        $queued = $this->countQueuedMatchJobs();
        $processed = $completed + $failed;
        $queueConnection = (string) config('queue.default');
        $workerNeeded = $queueConnection !== 'sync' && $queued > 0;
        $finished = $queued === 0 && $processed >= $total;

        return [
            'found' => true,
            'run_id' => $runId,
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'queued' => $queued,
            'processed' => $processed,
            'finished' => $finished,
            'queue_connection' => $queueConnection,
            'worker_needed' => $workerNeeded,
        ];
    }

    protected function countQueuedMatchJobs(): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        $table = (string) config('queue.connections.database.table', 'jobs');

        return (int) DB::table($table)
            ->where('payload', 'like', '%EvaluateJobMatchJob%')
            ->count();
    }

    protected function countFailedMatchJobs(string $startedAt): int
    {
        return (int) DB::table('failed_jobs')
            ->where('failed_at', '>=', $startedAt)
            ->where('payload', 'like', '%EvaluateJobMatchJob%')
            ->count();
    }

    protected function cacheKey(string $runId): string
    {
        return "job_match_run:{$runId}";
    }
}
