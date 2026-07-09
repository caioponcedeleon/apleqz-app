<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class RobotsTxtGuard
{
    /**
     * @throws ValidationException
     */
    public function assertAllowed(string $url): void
    {
        if ($this->isPathAllowed($url)) {
            return;
        }

        throw ValidationException::withMessages([
            'url' => 'Scraping this URL is disallowed by robots.txt.',
        ]);
    }

    public function isPathAllowed(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $path = $parts['path'] ?? '/';
        $robotsUrl = $this->robotsUrl($parts);

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => config('job_scraping.user_agent'),
                ])
                ->get($robotsUrl);
        } catch (\Throwable) {
            return true;
        }

        if (! $response->successful()) {
            return true;
        }

        return $this->pathAllowedInRobots($response->body(), $path);
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    protected function robotsUrl(array $parts): string
    {
        $scheme = strtolower($parts['scheme']);
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$host}{$port}/robots.txt";
    }

    protected function pathAllowedInRobots(string $robots, string $path): bool
    {
        $lines = preg_split('/\R/', $robots) ?: [];
        $activeAgent = false;
        $disallowed = [];

        foreach ($lines as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');

            if ($line === '') {
                continue;
            }

            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $matches) === 1) {
                $agent = strtolower(trim($matches[1]));
                $activeAgent = $agent === '*' || str_contains($agent, 'apleqz');

                continue;
            }

            if (! $activeAgent) {
                continue;
            }

            if (preg_match('/^Disallow:\s*(.*)$/i', $line, $matches) === 1) {
                $rule = trim($matches[1]);

                if ($rule !== '') {
                    $disallowed[] = $rule;
                }
            }
        }

        foreach ($disallowed as $rule) {
            if ($rule === '/') {
                return false;
            }

            if (str_starts_with($path, $rule)) {
                return false;
            }
        }

        return true;
    }
}
