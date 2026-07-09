<?php

namespace App\Support;

use App\Enums\JobExtractionEngine;
use Illuminate\Validation\ValidationException;

class JobExtractionConfigValidator
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function validate(array $config, bool $isActive): void
    {
        $errors = [];

        if (! isset($config['version']) || ! is_int($config['version'])) {
            $errors['extraction_config'] = 'The extraction config must include an integer "version" field.';
        }

        $engine = $config['engine'] ?? null;

        if (! is_string($engine) || ! in_array($engine, JobExtractionEngine::values(), true)) {
            $errors['extraction_config'] ??= 'The extraction config "engine" must be "http" or "playwright".';
        }

        if (! isset($config['listing']) || ! is_array($config['listing'])) {
            $errors['extraction_config'] ??= 'The extraction config must include a "listing" object.';
        }

        $itemSelector = $config['listing']['item_selector'] ?? null;

        $itemMode = is_string($config['listing']['item_mode'] ?? null)
            ? $config['listing']['item_mode']
            : 'single';
        $groupParts = is_array($config['listing']['item_group']['parts'] ?? null)
            ? $config['listing']['item_group']['parts']
            : [];

        if ($isActive && $itemMode === 'group') {
            if (count($groupParts) < 2) {
                $errors['extraction_config'] ??= 'Grouped list items require at least two part selectors.';
            }

            foreach ($groupParts as $index => $part) {
                $partSelector = is_array($part) ? ($part['selector'] ?? null) : null;

                if (! is_string($partSelector) || trim($partSelector) === '') {
                    $errors['extraction_config'] ??= 'Each grouped list item part requires a non-empty selector.';

                    break;
                }
            }
        } elseif ($isActive && ! is_string($itemSelector)) {
            $errors['extraction_config'] ??= 'Active job sources require listing.item_selector in the extraction config.';
        } elseif ($isActive && is_string($itemSelector) && trim($itemSelector) === '') {
            $errors['extraction_config'] ??= 'Active job sources require a non-empty listing.item_selector.';
        }

        if (isset($config['interactions']) && ! is_array($config['interactions'])) {
            $errors['extraction_config'] ??= 'The extraction config "interactions" must be an array.';
        }

        $pagination = $config['pagination'] ?? null;

        if ($pagination !== null && ! is_array($pagination)) {
            $errors['extraction_config'] ??= 'The extraction config "pagination" must be an object.';
        }

        if (is_array($pagination)) {
            $paginationType = $pagination['type'] ?? 'none';

            if (! in_array($paginationType, ['none', 'query_param'], true)) {
                $errors['extraction_config'] ??= 'Pagination type must be "none" or "query_param".';
            }

            if ($paginationType === 'query_param') {
                $param = $pagination['param'] ?? null;

                if (! is_string($param) || trim($param) === '') {
                    $errors['extraction_config'] ??= 'Query-parameter pagination requires a non-empty "param" name.';
                }

                $maxPages = $pagination['max_pages'] ?? null;

                if (! is_int($maxPages) || $maxPages < 1 || $maxPages > 50) {
                    $errors['extraction_config'] ??= 'Pagination max_pages must be an integer between 1 and 50.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
