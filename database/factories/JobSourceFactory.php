<?php

namespace Database\Factories;

use App\Enums\JobExtractionEngine;
use App\Models\JobSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobSource>
 */
class JobSourceFactory extends Factory
{
    protected $model = JobSource::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Careers',
            'url' => fake()->url(),
            'company_name' => fake()->company(),
            'is_active' => true,
            'extraction_config' => [
                'version' => 1,
                'engine' => JobExtractionEngine::Http->value,
                'sample_url' => null,
                'interactions' => [],
                'listing' => [
                    'item_selector' => 'article.job-card',
                    'fields' => [
                        'job_title' => [
                            'selector' => 'h2 a',
                            'scope' => 'item',
                            'extract' => 'text',
                        ],
                        'url' => [
                            'selector' => 'h2 a',
                            'scope' => 'item',
                            'extract' => 'attribute',
                            'attribute' => 'href',
                            'absolute' => true,
                        ],
                    ],
                ],
                'detail' => null,
                'pagination' => ['type' => 'none'],
            ],
            'config_version' => 1,
            'last_scraped_at' => null,
            'last_scrape_status' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
