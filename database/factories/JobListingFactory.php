<?php

namespace Database\Factories;

use App\Models\JobListing;
use App\Models\JobSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobListing>
 */
class JobListingFactory extends Factory
{
    protected $model = JobListing::class;

    public function definition(): array
    {
        $title = fake()->jobTitle();
        $url = fake()->url();

        return [
            'job_source_id' => JobSource::factory(),
            'external_id' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'title' => $title,
            'url' => $url,
            'company' => fake()->company(),
            'location' => fake()->city(),
            'salary' => null,
            'application_deadline' => null,
            'description' => fake()->paragraph(),
            'raw_fields' => null,
            'content_hash' => hash('sha256', $title.$url),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }
}
