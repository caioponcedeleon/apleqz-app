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

        $processEnv = $this->environmentForNodeProcess($scriptPath);
        $command = [$nodeBinary, $scriptPath];

        $process = new Process($command, base_path(), $processEnv);
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

    /**
     * PHP-FPM workers inherit a huge environment; proc_open must receive only this list
     * (never null — that would pass the full request environment to Node).
     *
     * @return array<string, string>
     */
    protected function environmentForNodeProcess(string $scriptPath): array
    {
        $home = config('job_scraping.playwright.home');
        $browsersPath = config('job_scraping.playwright.browsers_path');
        $path = config('job_scraping.playwright.path');
        $nodeOptions = config('job_scraping.playwright.node_options');

        if ($this->requiresPlaywrightRuntime($scriptPath)) {
            if (! is_string($home) || $home === '' || ! is_string($browsersPath) || $browsersPath === '') {
                throw new RuntimeException(
                    'Playwright is not configured for this server. Set JOB_SCRAPE_HOME and PLAYWRIGHT_BROWSERS_PATH in .env.',
                );
            }
        }

        $env = [
            'PATH' => is_string($path) && $path !== '' ? $path : '/usr/local/bin:/usr/bin:/bin',
        ];

        if (is_string($home) && $home !== '') {
            $env['HOME'] = $home;
        }

        if (is_string($browsersPath) && $browsersPath !== '') {
            $env['PLAYWRIGHT_BROWSERS_PATH'] = $browsersPath;
        }

        if (is_string($nodeOptions) && $nodeOptions !== '') {
            $env['NODE_OPTIONS'] = $nodeOptions;
        }

        $env['LANG'] = 'C.UTF-8';
        $env['LC_ALL'] = 'C.UTF-8';

        return $env;
    }

    protected function requiresPlaywrightRuntime(string $scriptPath): bool
    {
        return str_ends_with($scriptPath, 'scrape-page.mjs');
    }
}
