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

        $process = new Process(
            $this->commandForNodeScript($nodeBinary, $scriptPath),
            base_path(),
        );
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
     * @return list<string>
     */
    protected function commandForNodeScript(string $nodeBinary, string $scriptPath): array
    {
        if (PHP_OS_FAMILY !== 'Windows' && is_executable('/usr/bin/env')) {
            $command = ['env', '-i'];

            foreach ($this->environmentForNodeProcess() as $key => $value) {
                $command[] = $key.'='.$value;
            }

            $command[] = $nodeBinary;
            $command[] = $scriptPath;

            return $command;
        }

        return [$nodeBinary, $scriptPath];
    }

    /**
     * PHP-FPM workers can carry a huge environment; match the minimal CLI that works.
     *
     * @return array<string, string>
     */
    protected function environmentForNodeProcess(): array
    {
        $env = array_filter([
            'HOME' => config('job_scraping.playwright.home'),
            'PLAYWRIGHT_BROWSERS_PATH' => config('job_scraping.playwright.browsers_path'),
            'NODE_OPTIONS' => config('job_scraping.playwright.node_options'),
            'PATH' => config('job_scraping.playwright.path'),
        ], fn ($value) => is_string($value) && $value !== '');

        $env['PATH'] ??= '/usr/local/bin:/usr/bin:/bin';

        foreach (['LANG', 'LC_ALL', 'LC_CTYPE', 'TZ'] as $key) {
            $value = getenv($key);

            if (is_string($value) && $value !== '') {
                $env[$key] = $value;
            }
        }

        return $env;
    }
}
