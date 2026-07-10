<?php

namespace App\Services;

use App\Contracts\AiChatClient;
use App\Models\JobListing;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class JobMatchEvaluator
{
    public function __construct(
        protected AiChatClient $client,
    ) {}

    public function evaluationCacheKey(string $profileText, ?string $contentHash, ?string $excludeKeywords = null): string
    {
        return hash('sha256', trim($profileText).'|'.trim((string) $excludeKeywords).'|'.($contentHash ?? ''));
    }

    /**
     * @return array{fit_score: int, reason: string}
     */
    public function evaluate(string $profileText, JobListing $listing): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'You evaluate how well a job listing fits a candidate profile. '
                    .'Respond with JSON only using keys fit_score (integer 0-100) and reason (one short sentence).',
            ],
            [
                'role' => 'user',
                'content' => $this->buildPrompt($profileText, $listing),
            ],
        ];

        $lastError = null;

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $raw = $this->client->chat($messages, jsonObject: true);

                return $this->parseResponse($raw);
            } catch (RuntimeException|JsonException $exception) {
                $lastError = $exception;
            }
        }

        throw new RuntimeException(
            'Job match evaluation failed: '.($lastError?->getMessage() ?? 'Unknown error'),
            0,
            $lastError,
        );
    }

    protected function buildPrompt(string $profileText, JobListing $listing): string
    {
        $description = $listing->description;

        if (is_string($description) && $description !== '') {
            $description = Str::limit($description, (int) config('job_match.description_max_chars', 800));
        } else {
            $description = '—';
        }

        return implode("\n", [
            'Candidate profile:',
            trim($profileText),
            '',
            'Job listing:',
            'Title: '.$listing->title,
            'Company: '.($listing->company ?? '—'),
            'Location: '.($listing->location ?? '—'),
            'Description: '.$description,
        ]);
    }

    /**
     * @return array{fit_score: int, reason: string}
     */
    protected function parseResponse(string $raw): array
    {
        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI response was not a JSON object.');
        }

        $score = $decoded['fit_score'] ?? null;
        $reason = $decoded['reason'] ?? null;

        if (! is_numeric($score) || ! is_string($reason) || trim($reason) === '') {
            throw new RuntimeException('AI response missing fit_score or reason.');
        }

        $fitScore = max(0, min(100, (int) round((float) $score)));

        return [
            'fit_score' => $fitScore,
            'reason' => trim($reason),
        ];
    }
}
