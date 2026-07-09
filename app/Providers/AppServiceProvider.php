<?php

namespace App\Providers;

use App\Contracts\AiChatClient;
use App\Models\TranslationLine;
use App\Observers\TranslationLineObserver;
use App\Services\MistralCloudClient;
use App\Services\OllamaClient;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiChatClient::class, function (): AiChatClient {
            return match (config('job_match.driver')) {
                'mistral_cloud' => $this->app->make(MistralCloudClient::class),
                'ollama' => $this->app->make(OllamaClient::class),
                default => throw new InvalidArgumentException(
                    'Unsupported JOB_MATCH_AI_DRIVER: '.config('job_match.driver'),
                ),
            };
        });
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        TranslationLine::observe(TranslationLineObserver::class);
    }
}
