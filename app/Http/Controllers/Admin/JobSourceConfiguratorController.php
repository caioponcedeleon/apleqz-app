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
use App\Services\JobSourceConfigRevisionService;
use App\Support\JobExtractionConfigValidator;
use App\Support\JobListingGroupResolver;
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
            'itemMode' => is_string($config['listing']['item_mode'] ?? null)
                ? $config['listing']['item_mode']
                : 'single',
            'itemGroup' => is_array($config['listing']['item_group']['parts'] ?? null)
                ? $config['listing']['item_group']['parts']
                : [],
            'fieldMappings' => is_array($config['listing']['fields'] ?? null)
                ? $config['listing']['fields']
                : [],
            'pagination' => $this->paginationProps($config['pagination'] ?? null),
            'fieldOptions' => $this->fieldOptions(),
            'requiredFields' => JobListingField::requiredValues(),
        ]);
    }

    public function update(Request $request, JobSource $jobSource, JobExtractionConfigValidator $validator, JobSourceConfigRevisionService $revisions): RedirectResponse
    {
        $this->authorize('update', $jobSource);

        $validated = $request->validate([
            'preview_url' => ['required', 'url', 'max:2048'],
            'item_mode' => ['required', 'string', 'in:single,group'],
            'item_selector' => ['nullable', 'string', 'max:500'],
            'item_group' => ['nullable', 'array'],
            'item_group.parts' => ['nullable', 'array'],
            'fields' => ['nullable', 'array'],
            'pagination' => ['nullable', 'array'],
            'pagination.type' => ['nullable', 'string', 'in:none,query_param'],
            'pagination.param' => ['nullable', 'string', 'max:50'],
            'pagination.max_pages' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $itemMode = $validated['item_mode'];
        $groupParts = $this->normalizeGroupParts($validated['item_group']['parts'] ?? []);

        if ($itemMode === 'single' && blank($validated['item_selector'] ?? '')) {
            throw ValidationException::withMessages([
                'item_selector' => __('app.job_sources.configurator.item_selector_required'),
            ]);
        }

        if ($itemMode === 'group' && count($groupParts) < 2) {
            throw ValidationException::withMessages([
                'item_group' => __('app.job_sources.configurator.item_group_min_parts'),
            ]);
        }

        $config = $jobSource->extraction_config ?? JobSource::defaultExtractionConfig();
        $config['version'] = is_int($config['version'] ?? null) ? $config['version'] : 1;
        $config['engine'] = is_string($config['engine'] ?? null)
            ? $config['engine']
            : JobExtractionEngine::Http->value;
        $config['sample_url'] = $validated['preview_url'];
        $config['listing'] = [
            'item_mode' => $itemMode,
            'item_selector' => $itemMode === 'single' ? (string) ($validated['item_selector'] ?? '') : '',
            'item_group' => $itemMode === 'group' ? ['parts' => $groupParts] : null,
            'fields' => $validated['fields'] ?? [],
        ];
        $config['pagination'] = $this->normalizePagination($validated['pagination'] ?? null);

        $validator->validate($config, (bool) $jobSource->is_active);

        $revisions->snapshotBeforeUpdate($jobSource, $config);

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
            'item_selector' => ['required_without:item_group.parts', 'nullable', 'string', 'max:500'],
            'item_mode' => ['nullable', 'string', 'in:single,group'],
            'item_group' => ['nullable', 'array'],
            'item_group.parts' => ['nullable', 'array'],
            'fields' => ['nullable', 'array'],
        ]);

        $html = $validated['html'] ?? null;

        if ($html === null) {
            $html = $sanitizer->sanitize($fetcher->fetch($validated['url']), $validated['base_url']);
        }

        $config = [
            'listing' => [
                'item_mode' => $validated['item_mode'] ?? 'single',
                'item_selector' => $validated['item_selector'] ?? '',
                'item_group' => is_array($validated['item_group'] ?? null)
                    ? ['parts' => $this->normalizeGroupParts($validated['item_group']['parts'] ?? [])]
                    : null,
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

        $itemMatchCount = $this->countListingItems($html, $config['listing']);
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

    /**
     * @param  array<string, mixed>  $listingConfig
     */
    protected function countListingItems(string $html, array $listingConfig): int
    {
        $itemMode = is_string($listingConfig['item_mode'] ?? null)
            ? $listingConfig['item_mode']
            : 'single';
        $groupParts = is_array($listingConfig['item_group']['parts'] ?? null)
            ? $listingConfig['item_group']['parts']
            : [];

        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET | LIBXML_COMPACT,
        );
        libxml_clear_errors();

        $xpath = new \DOMXPath($document);
        $converter = new \Symfony\Component\CssSelector\CssSelectorConverter;

        if ($itemMode === 'group' && $groupParts !== []) {
            $resolver = app(JobListingGroupResolver::class);
            $partNodeLists = $resolver->partNodeLists($xpath, $converter, $groupParts);

            return $resolver->groupCount($partNodeLists);
        }

        $itemSelector = $listingConfig['item_selector'] ?? '';

        if (! is_string($itemSelector) || trim($itemSelector) === '') {
            return 0;
        }

        $nodes = $xpath->query($converter->toXPath($itemSelector));

        return $nodes === false ? 0 : $nodes->length;
    }

    /**
     * @param  list<mixed>  $parts
     * @return list<array{selector: string}>
     */
    protected function normalizeGroupParts(array $parts): array
    {
        $normalized = [];

        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }

            $selector = $part['selector'] ?? null;

            if (! is_string($selector) || trim($selector) === '') {
                continue;
            }

            $normalized[] = ['selector' => trim($selector)];
        }

        return $normalized;
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

    /**
     * @return array{type: string, param: string, max_pages: int}
     */
    protected function paginationProps(mixed $pagination): array
    {
        $normalized = $this->normalizePagination(is_array($pagination) ? $pagination : null);

        return [
            'type' => $normalized['type'],
            'param' => $normalized['param'],
            'max_pages' => $normalized['max_pages'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $pagination
     * @return array{type: string, param: string, max_pages: int, start: int, stop_when_empty: bool}
     */
    protected function normalizePagination(?array $pagination): array
    {
        $type = is_string($pagination['type'] ?? null) ? $pagination['type'] : 'none';

        if (! in_array($type, ['none', 'query_param'], true)) {
            $type = 'none';
        }

        return [
            'type' => $type,
            'param' => is_string($pagination['param'] ?? null) && $pagination['param'] !== ''
                ? $pagination['param']
                : 'page',
            'start' => 1,
            'max_pages' => max(1, min(50, (int) ($pagination['max_pages'] ?? 10))),
            'stop_when_empty' => true,
        ];
    }
}
