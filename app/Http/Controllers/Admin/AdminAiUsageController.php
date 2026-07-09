<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageRecord;
use App\Services\AiUsageRecorder;
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
        ]);
    }
}
