<?php

namespace App\Services;

use App\Enums\JobExtractionEngine;
use App\Models\JobListing;
use App\Models\JobSource;
use App\Support\PlaywrightInteractionPresets;
use Illuminate\Support\Str;

class JobListingDetailEnrichmentService
{
    public function __construct(
        protected JobSourceFetcher $fetcher,
        protected PlaywrightPageFetcher $playwrightFetcher,
        protected JobPreviewHtmlSanitizer $sanitizer,
        protected JobListingExtractor $extractor,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function detailConfigFor(JobSource $source): ?array
    {
        $config = $source->extraction_config ?? JobSource::defaultExtractionConfig();
        $detail = $config['detail'] ?? null;

        if (! is_array($detail) || ! ($detail['enabled'] ?? false)) {
            return null;
        }

        $fields = $detail['fields'] ?? [];

        if (! is_array($fields) || $fields === []) {
            return null;
        }

        return $detail;
    }

    /**
     * @return array{engine: string, interactions: list<array<string, mixed>>}
     */
    public function previewOptionsFor(JobListing $listing): array
    {
        $listing->loadMissing('jobSource');

        $source = $listing->jobSource;
        $sourceConfig = $source?->extraction_config ?? JobSource::defaultExtractionConfig();
        $engine = is_string($sourceConfig['engine'] ?? null)
            ? $sourceConfig['engine']
            : JobExtractionEngine::Http->value;
        $interactions = is_array($sourceConfig['interactions'] ?? null)
            ? $sourceConfig['interactions']
            : [];

        if ($source) {
            $detail = $this->detailConfigFor($source);

            if (is_array($detail)) {
                $detailEngine = is_string($detail['engine'] ?? null) ? $detail['engine'] : 'inherit';

                if ($detailEngine !== 'inherit') {
                    $engine = $detailEngine;
                }

                $detailInteractions = is_array($detail['interactions'] ?? null) ? $detail['interactions'] : [];

                if ($detailInteractions !== []) {
                    $interactions = $detailInteractions;
                }
            }
        }

        return [
            'engine' => $engine,
            'interactions' => $interactions,
        ];
    }

    public function enrich(JobListing $listing): bool
    {
        $listing->loadMissing('jobSource');

        $source = $listing->jobSource;

        if (! $source) {
            return false;
        }

        $detail = $this->detailConfigFor($source);

        if ($detail === null) {
            return false;
        }

        if ($listing->detail_enriched_at !== null) {
            return false;
        }

        $url = trim($listing->url);

        if ($url === '') {
            return false;
        }

        $sourceConfig = $source->extraction_config ?? JobSource::defaultExtractionConfig();
        $engine = is_string($detail['engine'] ?? null) ? $detail['engine'] : 'inherit';

        if ($engine === 'inherit') {
            $engine = is_string($sourceConfig['engine'] ?? null)
                ? $sourceConfig['engine']
                : JobExtractionEngine::Http->value;
        }

        $interactions = is_array($detail['interactions'] ?? null) ? $detail['interactions'] : [];

        if ($interactions === [] && is_array($sourceConfig['interactions'] ?? null)) {
            $interactions = $sourceConfig['interactions'];
        }

        $usePlaywright = $engine === JobExtractionEngine::Playwright->value || $interactions !== [];
        $interactions = PlaywrightInteractionPresets::resolve($interactions, $usePlaywright);

        $html = $usePlaywright
            ? $this->playwrightFetcher->fetch($url, $interactions)
            : $this->fetcher->fetch($url);

        $html = $this->sanitizer->sanitize($html, $url);
        $fields = is_array($detail['fields'] ?? null) ? $detail['fields'] : [];
        $rawFields = $this->extractor->extractDetail($html, $fields, $url);
        $mergedRawFields = array_merge(
            is_array($listing->raw_fields) ? $listing->raw_fields : [],
            $rawFields,
        );

        $title = $this->limitedField($rawFields['job_title'] ?? null) ?? $listing->title;
        $description = $this->cleanText($rawFields['description'] ?? null) ?? $listing->description;
        $location = $this->limitedField($rawFields['location'] ?? null) ?? $listing->location;
        $salary = $this->limitedField($rawFields['salary'] ?? null) ?? $listing->salary;
        $applicationDeadline = $this->limitedField($rawFields['application_deadline'] ?? null)
            ?? $listing->application_deadline;
        $company = $this->limitedField($rawFields['company'] ?? null) ?? $listing->company;

        $listing->update([
            'title' => $title,
            'description' => $description,
            'location' => $location,
            'salary' => $salary,
            'application_deadline' => $applicationDeadline,
            'company' => $company,
            'raw_fields' => $mergedRawFields,
            'content_hash' => hash('sha256', $title.($description ?? '')),
            'detail_enriched_at' => now(),
        ]);

        return true;
    }

    protected function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : $value;
    }

    protected function limitedField(?string $value): ?string
    {
        $value = $this->cleanText($value);

        return $value ? Str::limit($value, 255, '') : null;
    }
}
