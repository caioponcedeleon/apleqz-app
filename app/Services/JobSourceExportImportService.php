<?php

namespace App\Services;

use App\Models\JobSource;
use App\Support\JobExtractionConfigValidator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class JobSourceExportImportService
{
    public const SCHEMA_VERSION = 1;

    /**
     * @return array{
     *     schema_version: int,
     *     exported_at: string,
     *     sources: list<array<string, mixed>>
     * }
     */
    public function export(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'exported_at' => now()->toIso8601String(),
            'sources' => JobSource::query()
                ->orderBy('company_name')
                ->orderBy('name')
                ->get()
                ->map(fn (JobSource $source): array => [
                    'name' => $source->name,
                    'url' => $source->url,
                    'company_name' => $source->company_name,
                    'is_active' => $source->is_active,
                    'extraction_config' => $source->extraction_config ?? JobSource::defaultExtractionConfig(),
                    'config_version' => $source->config_version ?? 1,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function import(array $payload): array
    {
        $this->assertValidPayload($payload);

        $validator = app(JobExtractionConfigValidator::class);
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($payload['sources'] as $index => $sourceData) {
            if (! is_array($sourceData)) {
                $errors[] = $this->sourceError($index, 'Each source must be an object.');

                continue;
            }

            try {
                $normalized = $this->normalizeSource($sourceData);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $this->sourceError($index, $exception->getMessage(), $sourceData);

                continue;
            }

            try {
                $validator->validate($normalized['extraction_config'], $normalized['is_active']);
            } catch (ValidationException) {
                if ($normalized['is_active']) {
                    $normalized['is_active'] = false;
                }

                try {
                    $validator->validate($normalized['extraction_config'], false);
                } catch (ValidationException $exception) {
                    $errors[] = $this->sourceError(
                        $index,
                        collect($exception->errors())->flatten()->first() ?? 'Invalid extraction config.',
                        $sourceData,
                    );

                    continue;
                }
            }

            $existing = JobSource::query()->where('url', $normalized['url'])->first();

            if ($existing) {
                $existing->update([
                    'name' => $normalized['name'],
                    'company_name' => $normalized['company_name'],
                    'is_active' => $normalized['is_active'],
                    'extraction_config' => $normalized['extraction_config'],
                    'config_version' => $normalized['config_version'],
                    'last_scraped_at' => null,
                    'last_scrape_status' => null,
                ]);
                $updated++;

                continue;
            }

            JobSource::query()->create([
                'name' => $normalized['name'],
                'url' => $normalized['url'],
                'company_name' => $normalized['company_name'],
                'is_active' => $normalized['is_active'],
                'extraction_config' => $normalized['extraction_config'],
                'config_version' => $normalized['config_version'],
            ]);
            $created++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function assertValidPayload(array $payload): void
    {
        if (($payload['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw ValidationException::withMessages([
                'file' => __('app.job_sources.import_invalid_schema'),
            ]);
        }

        if (! isset($payload['sources']) || ! is_array($payload['sources'])) {
            throw ValidationException::withMessages([
                'file' => __('app.job_sources.import_invalid_payload'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $sourceData
     * @return array{
     *     name: string,
     *     url: string,
     *     company_name: string|null,
     *     is_active: bool,
     *     extraction_config: array<string, mixed>,
     *     config_version: int
     * }
     */
    protected function normalizeSource(array $sourceData): array
    {
        $name = trim((string) ($sourceData['name'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Missing source name.');
        }

        $url = trim((string) ($sourceData['url'] ?? ''));

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Missing or invalid source URL.');
        }

        $companyName = $sourceData['company_name'] ?? null;
        $companyName = is_string($companyName) && trim($companyName) !== ''
            ? trim($companyName)
            : null;

        $extractionConfig = $sourceData['extraction_config'] ?? JobSource::defaultExtractionConfig();

        if (! is_array($extractionConfig)) {
            throw new InvalidArgumentException('Extraction config must be an object.');
        }

        $configVersion = $sourceData['config_version'] ?? 1;

        if (! is_int($configVersion)) {
            $configVersion = (int) $configVersion;
        }

        if ($configVersion < 1) {
            $configVersion = 1;
        }

        return [
            'name' => $name,
            'url' => $url,
            'company_name' => $companyName,
            'is_active' => (bool) ($sourceData['is_active'] ?? false),
            'extraction_config' => $extractionConfig,
            'config_version' => $configVersion,
        ];
    }

    /**
     * @param  array<string, mixed>  $sourceData
     */
    protected function sourceError(int $index, string $message, array $sourceData = []): string
    {
        $label = trim((string) ($sourceData['name'] ?? ''));

        if ($label === '') {
            $label = trim((string) ($sourceData['url'] ?? ''));

            if ($label === '') {
                $label = '#'.($index + 1);
            }
        }

        return "{$label}: {$message}";
    }
}
