<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\CssSelector\CssSelectorConverter;

class JobListingExtractor
{
    /**
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    public function extract(string $html, array $config, string $baseUrl, ?string $defaultCompany = null): array
    {
        $listingConfig = $config['listing'] ?? null;

        if (! is_array($listingConfig)) {
            throw new RuntimeException('Extraction config is missing a listing section.');
        }

        $itemSelector = $listingConfig['item_selector'] ?? '';

        if (! is_string($itemSelector) || trim($itemSelector) === '') {
            throw new RuntimeException('Extraction config is missing listing.item_selector.');
        }

        $document = $this->loadHtml($html);
        $xpath = new DOMXPath($document);
        $converter = new CssSelectorConverter;
        $itemXPath = $converter->toXPath($itemSelector);
        $items = $xpath->query($itemXPath);

        if ($items === false) {
            throw new RuntimeException('Could not evaluate the listing item selector.');
        }

        $fields = is_array($listingConfig['fields'] ?? null) ? $listingConfig['fields'] : [];
        $listings = [];

        foreach ($items as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }

            $rawFields = $this->extractFields($item, $fields, $xpath, $converter, $baseUrl);
            $normalized = $this->normalizeListing($rawFields, $defaultCompany);

            if ($normalized === null) {
                continue;
            }

            $listings[] = $normalized;
        }

        return $listings;
    }

    protected function loadHtml(string $html): DOMDocument
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET | LIBXML_COMPACT,
        );
        libxml_clear_errors();

        return $document;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, string|null>
     */
    protected function extractFields(
        DOMElement $item,
        array $fields,
        DOMXPath $xpath,
        CssSelectorConverter $converter,
        string $baseUrl,
    ): array {
        $values = [];

        foreach ($fields as $fieldKey => $fieldConfig) {
            if (! is_string($fieldKey) || ! is_array($fieldConfig)) {
                continue;
            }

            $value = $this->extractFieldValue($item, $fieldConfig, $xpath, $converter, $baseUrl);

            if ($value === null && ($fieldConfig['optional'] ?? false)) {
                continue;
            }

            $values[$fieldKey] = $value;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $fieldConfig
     */
    protected function extractFieldValue(
        DOMElement $item,
        array $fieldConfig,
        DOMXPath $xpath,
        CssSelectorConverter $converter,
        string $baseUrl,
    ): ?string {
        if (($fieldConfig['source'] ?? null) === 'static') {
            $value = $fieldConfig['value'] ?? null;

            return is_string($value) ? trim($value) : null;
        }

        $selector = $fieldConfig['selector'] ?? null;

        if (! is_string($selector) || trim($selector) === '') {
            return null;
        }

        $scope = $fieldConfig['scope'] ?? 'item';
        $context = $scope === 'document' ? $item->ownerDocument?->documentElement : $item;

        if (! $context instanceof DOMElement) {
            return null;
        }

        $nodes = $xpath->query($converter->toXPath($selector), $context);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);

        if (! $node instanceof DOMElement) {
            return null;
        }

        $extract = $fieldConfig['extract'] ?? 'text';

        if ($extract === 'attribute') {
            $attribute = $fieldConfig['attribute'] ?? null;

            if (! is_string($attribute) || $attribute === '') {
                return null;
            }

            $value = trim($node->getAttribute($attribute));

            if ($value === '') {
                return null;
            }

            if (($fieldConfig['absolute'] ?? false) && in_array($attribute, ['href', 'src'], true)) {
                return $this->resolveUrl($value, $baseUrl);
            }

            return $value;
        }

        return trim($node->textContent);
    }

    /**
     * @param  array<string, string|null>  $rawFields
     * @return array<string, mixed>|null
     */
    protected function normalizeListing(array $rawFields, ?string $defaultCompany): ?array
    {
        $title = $this->cleanText($rawFields['job_title'] ?? null);
        $url = $this->cleanText($rawFields['url'] ?? null);

        if ($title === null || $url === null) {
            return null;
        }

        $company = $this->cleanText($rawFields['company'] ?? null) ?? $defaultCompany;
        $description = $this->cleanText($rawFields['description'] ?? null);

        $externalId = $this->cleanText($rawFields['external_id'] ?? null);

        if ($externalId === null) {
            $externalId = hash('sha256', $url);
        }

        return [
            'external_id' => Str::limit($externalId, 255, ''),
            'title' => Str::limit($title, 255, ''),
            'url' => Str::limit($url, 2048, ''),
            'company' => $company ? Str::limit($company, 255, '') : null,
            'location' => $this->limitedField($rawFields['location'] ?? null),
            'salary' => $this->limitedField($rawFields['salary'] ?? null),
            'application_deadline' => $this->limitedField($rawFields['application_deadline'] ?? null),
            'description' => $description,
            'raw_fields' => $rawFields,
            'content_hash' => hash('sha256', $title.($description ?? '')),
        ];
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

    protected function resolveUrl(string $value, string $baseUrl): string
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        $baseParts = parse_url($baseUrl);

        if (! is_array($baseParts) || empty($baseParts['scheme']) || empty($baseParts['host'])) {
            return $value;
        }

        $scheme = $baseParts['scheme'];
        $host = $baseParts['host'];
        $port = isset($baseParts['port']) ? ':'.$baseParts['port'] : '';

        if (str_starts_with($value, '//')) {
            return $scheme.':'.$value;
        }

        if (str_starts_with($value, '/')) {
            return "{$scheme}://{$host}{$port}{$value}";
        }

        $path = $baseParts['path'] ?? '/';
        $directory = str_ends_with($path, '/') ? $path : dirname($path).'/';

        return "{$scheme}://{$host}{$port}{$directory}{$value}";
    }
}
