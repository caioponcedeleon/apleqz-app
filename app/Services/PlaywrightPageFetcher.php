<?php

namespace App\Services;

use App\Support\ScrapeUrlGuard;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class PlaywrightPageFetcher
{
    public function __construct(
        protected ScrapeUrlGuard $urlGuard,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $interactions
     *
     * @throws ValidationException
     */
    public function fetch(string $url, array $interactions = [], ?int $timeoutMs = null): string
    {
        $this->urlGuard->assertSafe($url);

        $timeoutMs = $timeoutMs ?? (int) config('job_scraping.playwright.timeout_ms', 60_000);
        $maxBytes = (int) config('job_scraping.max_bytes', 2_097_152);
        $nodeBinary = (string) config('job_scraping.playwright.node_binary', 'node');
        $scriptPath = (string) config('job_scraping.playwright.script_path');

        if ($scriptPath === '' || ! is_file($scriptPath)) {
            throw new RuntimeException('Playwright scrape script was not found.');
        }

        $payload = json_encode([
            'url' => $url,
            'interactions' => $interactions,
            'timeout_ms' => $timeoutMs,
        ], JSON_THROW_ON_ERROR);

        $process = new Process([$nodeBinary, $scriptPath]);
        $process->setInput($payload);
        $process->setTimeout(max(1, (int) ceil($timeoutMs / 1000) + 30));

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException(
                'Playwright scrape timed out.',
                previous: $exception,
            );
        }

        $decoded = json_decode(trim($process->getOutput()), true);
        $error = is_array($decoded) && is_string($decoded['error'] ?? null)
            ? $decoded['error']
            : null;

        if (! $process->isSuccessful()) {
            $fallback = trim($process->getErrorOutput()) !== ''
                ? trim($process->getErrorOutput())
                : 'Unknown Playwright error.';

            throw new RuntimeException(
                'Playwright scrape failed: '.($error ?? $fallback),
            );
        }

        $html = is_array($decoded) && is_string($decoded['html'] ?? null)
            ? $decoded['html']
            : null;

        if ($html === null || $html === '') {
            throw new RuntimeException(
                'Playwright scrape returned an empty HTML response.',
            );
        }

        if (strlen($html) > $maxBytes) {
            throw new RuntimeException(
                'Playwright HTML exceeds the maximum allowed size.',
            );
        }

        return $html;
    }
}
