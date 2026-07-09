<?php

namespace App\Services;

use App\Enums\JobExtractionEngine;
use App\Support\DynamicHtmlPlaceholderDetector;
use App\Support\PlaywrightInteractionPresets;
use App\Support\ScrapeUrlGuard;
use RuntimeException;

class JobSourcePreviewService
{
    public function __construct(
        protected ScrapeUrlGuard $urlGuard,
        protected JobSourceFetcher $fetcher,
        protected PlaywrightPageFetcher $playwrightFetcher,
        protected JobPreviewHtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{engine?: string, interactions?: list<array<string, mixed>>}  $options
     * @return array{html: string, rendered_with: string, suggest_playwright: bool}
     */
    public function prepare(string $url, array $options = []): array
    {
        $this->urlGuard->assertSafe($url);

        $engine = is_string($options['engine'] ?? null)
            ? $options['engine']
            : JobExtractionEngine::Http->value;
        $interactions = is_array($options['interactions'] ?? null)
            ? $options['interactions']
            : [];
        $usePlaywright = $engine === JobExtractionEngine::Playwright->value || $interactions !== [];
        $suggestPlaywright = false;

        if ($usePlaywright) {
            $interactions = PlaywrightInteractionPresets::resolve($interactions, true);
            $html = $this->playwrightFetcher->fetch($url, $interactions);
            $renderedWith = JobExtractionEngine::Playwright->value;
        } else {
            $html = $this->fetcher->fetch($url);
            $suggestPlaywright = DynamicHtmlPlaceholderDetector::suggestsPlaywright($html);
            $renderedWith = JobExtractionEngine::Http->value;
        }

        $sanitized = $this->sanitizer->sanitize($html, $url);

        $pickerUrl = asset('js/job-source-picker.js');

        if (! is_string($pickerUrl) || $pickerUrl === '') {
            throw new RuntimeException('Could not resolve the picker script URL.');
        }

        return [
            'html' => $this->sanitizer->injectPickerScript($sanitized, $pickerUrl),
            'rendered_with' => $renderedWith,
            'suggest_playwright' => $suggestPlaywright,
        ];
    }
}
