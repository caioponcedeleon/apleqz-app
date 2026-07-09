<?php

namespace App\Services;

use App\Contracts\AiChatClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaClient implements AiChatClient
{
    public function __construct(
        protected AiUsageRecorder $usageRecorder,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, bool $jsonObject = false): string
    {
        $model = (string) config('job_match.ollama.model');

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
        ];

        if ($jsonObject) {
            $payload['format'] = 'json';
        }

        $response = Http::baseUrl(rtrim((string) config('job_match.ollama.base_url'), '/'))
            ->acceptJson()
            ->timeout(120)
            ->post('/chat/completions', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Ollama API request failed: '.$response->status().' '.$response->body(),
            );
        }

        $usage = $response->json('usage');

        if (is_array($usage)) {
            $this->usageRecorder->record('ollama', $model, $usage, 'job_match');
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Ollama API returned an empty response.');
        }

        return trim($content);
    }
}
