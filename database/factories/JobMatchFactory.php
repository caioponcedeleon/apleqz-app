<?php

namespace Database\Factories;

use App\Enums\JobMatchStatus;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobMatch>
 */
class JobMatchFactory extends Factory
{
    protected $model = JobMatch::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'job_listing_id' => JobListing::factory(),
            'fit_score' => fake()->numberBetween(70, 95),
            'reason' => fake()->sentence(),
            'status' => JobMatchStatus::PendingNotify,
            'evaluation_cache_key' => fake()->sha256(),
            'notified_at' => null,
        ];
    }
}
