<?php

namespace App\Services;

use App\Models\AiUsageRecord;
use App\Support\AiUsageContext;

class AiUsageRecorder
{
    /**
     * @param  array<string, mixed>|null  $usage
     */
    public function record(string $driver, string $model, ?array $usage, ?string $purpose = null): void
    {
        $promptTokens = is_numeric($usage['prompt_tokens'] ?? null)
            ? (int) $usage['prompt_tokens']
            : 0;
        $completionTokens = is_numeric($usage['completion_tokens'] ?? null)
            ? (int) $usage['completion_tokens']
            : 0;
        $totalTokens = is_numeric($usage['total_tokens'] ?? null)
            ? (int) $usage['total_tokens']
            : ($promptTokens + $completionTokens);

        $context = AiUsageContext::current();

        AiUsageRecord::query()->create([
            'driver' => $driver,
            'model' => $model,
            'purpose' => $purpose ?? ($context['purpose'] ?? 'unknown'),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'user_id' => $context['user_id'] ?? null,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array{
     *     request_count: int,
     *     prompt_tokens: int,
     *     completion_tokens: int,
     *     total_tokens: int,
     *     estimated_cost_eur: float|null
     * }
     */
    public function summarize(?string $driver = null, ?string $model = null): array
    {
        $query = AiUsageRecord::query();

        if ($driver !== null) {
            $query->where('driver', $driver);
        }

        if ($model !== null) {
            $query->where('model', $model);
        }

        $promptTokens = (int) $query->clone()->sum('prompt_tokens');
        $completionTokens = (int) $query->clone()->sum('completion_tokens');
        $totalTokens = (int) $query->clone()->sum('total_tokens');
        $requestCount = (int) $query->clone()->count();

        return [
            'request_count' => $requestCount,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost_eur' => $this->estimateCostEur($model, $promptTokens, $completionTokens),
        ];
    }

    public function estimateCostEur(?string $model, int $promptTokens, int $completionTokens): ?float
    {
        if ($model === null) {
            return null;
        }

        $pricing = config("job_match.pricing.{$model}");

        if (! is_array($pricing)) {
            return null;
        }

        $inputPerMillion = (float) ($pricing['input'] ?? 0);
        $outputPerMillion = (float) ($pricing['output'] ?? 0);

        if ($inputPerMillion <= 0 && $outputPerMillion <= 0) {
            return null;
        }

        return round(
            ($promptTokens * $inputPerMillion / 1_000_000)
            + ($completionTokens * $outputPerMillion / 1_000_000),
            4,
        );
    }
}
