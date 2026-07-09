<?php

namespace App\Services;

use App\Support\ScrapeUrlGuard;
use RuntimeException;

class JobSourcePreviewService
{
    public function __construct(
        protected ScrapeUrlGuard $urlGuard,
        protected JobSourceFetcher $fetcher,
        protected JobPreviewHtmlSanitizer $sanitizer,
    ) {}

    public function prepare(string $url): string
    {
        $this->urlGuard->assertSafe($url);

        $html = $this->fetcher->fetch($url);
        $sanitized = $this->sanitizer->sanitize($html, $url);

        $pickerUrl = asset('js/job-source-picker.js');

        if (! is_string($pickerUrl) || $pickerUrl === '') {
            throw new RuntimeException('Could not resolve the picker script URL.');
        }

        return $this->sanitizer->injectPickerScript($sanitized, $pickerUrl);
    }
}
