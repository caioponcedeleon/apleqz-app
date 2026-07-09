<?php

namespace App\Services;

use App\Contracts\AiChatClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MistralCloudClient implements AiChatClient
{
    public function __construct(
        protected AiUsageRecorder $usageRecorder,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, bool $jsonObject = false): string
    {
        $apiKey = config('job_match.mistral.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('MISTRAL_API_KEY is not configured.');
        }

        $model = (string) config('job_match.mistral.model');

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if ($jsonObject) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::baseUrl(rtrim((string) config('job_match.mistral.base_url'), '/'))
            ->withToken($apiKey)
            ->acceptJson()
            ->timeout(60)
            ->post('/chat/completions', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Mistral API request failed: '.$response->status().' '.$response->body(),
            );
        }

        $usage = $response->json('usage');

        if (is_array($usage)) {
            $this->usageRecorder->record('mistral_cloud', $model, $usage, 'job_match');
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Mistral API returned an empty response.');
        }

        return trim($content);
    }
}
