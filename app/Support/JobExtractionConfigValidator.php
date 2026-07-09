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

        if ($isActive && ! is_string($itemSelector)) {
            $errors['extraction_config'] ??= 'Active job sources require listing.item_selector in the extraction config.';
        }

        if ($isActive && is_string($itemSelector) && trim($itemSelector) === '') {
            $errors['extraction_config'] ??= 'Active job sources require a non-empty listing.item_selector.';
        }

        if (isset($config['interactions']) && ! is_array($config['interactions'])) {
            $errors['extraction_config'] ??= 'The extraction config "interactions" must be an array.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
