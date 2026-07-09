<?php

namespace Tests\Unit;

use App\Models\JobSource;
use App\Support\JobExtractionConfigValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JobExtractionConfigValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_valid_config_for_active_source(): void
    {
        $config = JobSource::factory()->make()->extraction_config;

        app(JobExtractionConfigValidator::class)->validate($config, isActive: true);

        $this->assertTrue(true);
    }

    public function test_rejects_active_source_without_item_selector(): void
    {
        $config = JobSource::defaultExtractionConfig();

        $this->expectException(ValidationException::class);

        app(JobExtractionConfigValidator::class)->validate($config, isActive: true);
    }

    public function test_allows_inactive_source_without_item_selector(): void
    {
        $config = JobSource::defaultExtractionConfig();

        app(JobExtractionConfigValidator::class)->validate($config, isActive: false);

        $this->assertTrue(true);
    }

    public function test_rejects_invalid_engine(): void
    {
        $config = JobSource::factory()->make()->extraction_config;
        $config['engine'] = 'selenium';

        $this->expectException(ValidationException::class);

        app(JobExtractionConfigValidator::class)->validate($config, isActive: true);
    }
}
