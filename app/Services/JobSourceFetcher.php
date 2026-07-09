<?php

namespace App\Services;

use App\Support\HtmlEncodingNormalizer;
use App\Support\ScrapeUrlGuard;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class JobSourceFetcher
{
    public function __construct(
        protected ScrapeUrlGuard $urlGuard,
        protected HtmlEncodingNormalizer $encoding,
    ) {}

    /**
     * @throws ValidationException
     */
    public function fetch(string $url): string
    {
        $this->urlGuard->assertSafe($url);

        $timeout = config('job_scraping.http_timeout', 15);
        $maxBytes = config('job_scraping.max_bytes', 2_097_152);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent' => config('job_scraping.user_agent'),
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Failed to fetch job source URL: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'Job source URL returned HTTP '.$response->status().'.',
            );
        }

        $body = $this->encoding->toUtf8(
            $response->body(),
            $response->header('Content-Type'),
        );

        if (strlen($body) > $maxBytes) {
            throw new RuntimeException(
                'Job source HTML exceeds the maximum allowed size.',
            );
        }

        return $body;
    }
}
