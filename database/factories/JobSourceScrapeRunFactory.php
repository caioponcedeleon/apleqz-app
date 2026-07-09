<?php

namespace Database\Factories;

use App\Enums\JobScrapeStatus;
use App\Models\JobSource;
use App\Models\JobSourceScrapeRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobSourceScrapeRun>
 */
class JobSourceScrapeRunFactory extends Factory
{
    protected $model = JobSourceScrapeRun::class;

    public function definition(): array
    {
        $startedAt = now()->subMinutes(5);

        return [
            'job_source_id' => JobSource::factory(),
            'started_at' => $startedAt,
            'finished_at' => now(),
            'status' => JobScrapeStatus::Success,
            'listings_found' => fake()->numberBetween(0, 20),
            'listings_new' => fake()->numberBetween(0, 5),
            'error_message' => null,
            'meta' => ['engine' => 'http'],
        ];
    }
}
