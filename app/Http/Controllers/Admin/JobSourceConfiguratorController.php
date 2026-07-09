<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JobExtractionEngine;
use App\Enums\JobListingField;
use App\Http\Controllers\Controller;
use App\Models\JobSource;
use App\Services\JobListingExtractor;
use App\Services\JobPreviewHtmlSanitizer;
use App\Services\JobSourceFetcher;
use App\Services\JobSourcePreviewService;
use App\Support\JobExtractionConfigValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JobSourceConfiguratorController extends Controller
{
    public function edit(JobSource $jobSource): Response
    {
        $this->authorize('update', $jobSource);

        $config = $jobSource->extraction_config ?? JobSource::defaultExtractionConfig();
        $sampleUrl = is_string($config['sample_url'] ?? null) && $config['sample_url'] !== ''
            ? $config['sample_url']
            : $jobSource->url;

        return Inertia::render('Admin/JobSources/Configure', [
            'jobSource' => $jobSource->only(['id', 'name', 'url', 'company_name', 'is_active', 'config_version']),
            'previewUrl' => $sampleUrl,
            'itemSelector' => is_string($config['listing']['item_selector'] ?? null)
                ? $config['listing']['item_selector']
                : '',
            'fieldMappings' => is_array($config['listing']['fields'] ?? null)
                ? $config['listing']['fields']
                : [],
            'fieldOptions' => $this->fieldOptions(),
            'requiredFields' => JobListingField::requiredValues(),
        ]);
    }

    public function update(Request $request, JobSource $jobSource, JobExtractionConfigValidator $validator): RedirectResponse
    {
        $this->authorize('update', $jobSource);

        $validated = $request->validate([
            'preview_url' => ['required', 'url', 'max:2048'],
            'item_selector' => ['required', 'string', 'max:500'],
            'fields' => ['nullable', 'array'],
        ]);

        $config = $jobSource->extraction_config ?? JobSource::defaultExtractionConfig();
        $config['version'] = is_int($config['version'] ?? null) ? $config['version'] : 1;
        $config['engine'] = is_string($config['engine'] ?? null)
            ? $config['engine']
            : JobExtractionEngine::Http->value;
        $config['sample_url'] = $validated['preview_url'];
        $config['listing'] = [
            'item_selector' => $validated['item_selector'],
            'fields' => $validated['fields'] ?? [],
        ];

        $validator->validate($config, (bool) $jobSource->is_active);

        $jobSource->update([
            'extraction_config' => $config,
            'config_version' => ($jobSource->config_version ?? 0) + 1,
        ]);

        return redirect()
            ->route('job-sources.configure', $jobSource)
            ->with('success', __('app.job_sources.flash.config_saved'));
    }

    public function preview(Request $request, JobSourcePreviewService $preview): JsonResponse
    {
        $this->authorize('viewAny', JobSource::class);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $displayHtml = $preview->prepare($validated['url']);
        $cachedHtml = preg_replace(
            '/<script\b[^>]*job-source-picker\.js[^>]*><\/script>/i',
            '',
            $displayHtml,
        ) ?? $displayHtml;

        return response()->json([
            'html' => $displayHtml,
            'cached_html' => $cachedHtml,
        ]);
    }

    public function testExtraction(
        Request $request,
        JobListingExtractor $extractor,
        JobSourceFetcher $fetcher,
        JobPreviewHtmlSanitizer $sanitizer,
    ): JsonResponse {
        $this->authorize('viewAny', JobSource::class);

        $validated = $request->validate([
            'html' => ['required_without:url', 'string'],
            'url' => ['required_without:html', 'url', 'max:2048'],
            'base_url' => ['required', 'url', 'max:2048'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'item_selector' => ['required', 'string', 'max:500'],
            'fields' => ['nullable', 'array'],
        ]);

        $html = $validated['html'] ?? null;

        if ($html === null) {
            $html = $sanitizer->sanitize($fetcher->fetch($validated['url']), $validated['base_url']);
        }

        $config = [
            'listing' => [
                'item_selector' => $validated['item_selector'],
                'fields' => $validated['fields'] ?? [],
            ],
        ];

        try {
            $listings = $extractor->extract(
                $html,
                $config,
                $validated['base_url'],
                $validated['company_name'] ?? null,
            );
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'extraction' => $exception->getMessage(),
            ]);
        }

        $itemMatchCount = $this->countSelectorMatches($html, $validated['item_selector']);
        $fieldKeys = array_keys($validated['fields'] ?? []);

        return response()->json([
            'listings' => array_map(fn (array $listing): array => [
                'fields' => $this->listingFieldsForPreview($listing, $fieldKeys),
            ], $listings),
            'count' => count($listings),
            'item_match_count' => $itemMatchCount,
        ]);
    }

    /**
     * @param  array<string, mixed>  $listing
     * @param  list<string>  $fieldKeys
     * @return array<string, string|null>
     */
    protected function listingFieldsForPreview(array $listing, array $fieldKeys): array
    {
        $rawFields = is_array($listing['raw_fields'] ?? null) ? $listing['raw_fields'] : [];
        $preview = [];

        foreach ($fieldKeys as $fieldKey) {
            $value = $rawFields[$fieldKey] ?? null;

            if ($fieldKey === 'job_title' && $value === null) {
                $value = $listing['title'] ?? null;
            }

            if ($fieldKey === 'url' && $value === null) {
                $value = $listing['url'] ?? null;
            }

            if ($fieldKey === 'company' && $value === null) {
                $value = $listing['company'] ?? null;
            }

            if ($fieldKey === 'location' && $value === null) {
                $value = $listing['location'] ?? null;
            }

            $preview[$fieldKey] = is_string($value) && trim($value) !== '' ? $value : null;
        }

        return $preview;
    }

    /**
     * @return array<string, string>
     */
    protected function fieldOptions(): array
    {
        $options = collect(JobListingField::cases())
            ->mapWithKeys(fn (JobListingField $field): array => [
                $field->value => $this->fieldLabel($field->value),
            ])
            ->all();

        $options['__custom__'] = __('app.job_sources.fields.custom');

        return $options;
    }

    protected function fieldLabel(string $field): string
    {
        $key = "app.job_sources.fields.{$field}";
        $translated = __($key);

        return $translated !== $key
            ? $translated
            : config("job_listing_fields.{$field}", str_replace('_', ' ', ucfirst($field)));
    }

    protected function countSelectorMatches(string $html, string $selector): int
    {
        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET | LIBXML_COMPACT,
        );
        libxml_clear_errors();

        $xpath = new \DOMXPath($document);
        $converter = new \Symfony\Component\CssSelector\CssSelectorConverter;
        $nodes = $xpath->query($converter->toXPath($selector));

        return $nodes === false ? 0 : $nodes->length;
    }
}
