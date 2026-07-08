<?php

namespace Database\Factories;

use App\Enums\ApplicationMomentType;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationWave;
use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        $status = fake()->randomElement(ApplicationStatus::cases());
        $appliedAt = $status === ApplicationStatus::WaitingToApply
            ? fake()->optional(0.4)->dateTimeBetween('now', '+2 months')
            : fake()->dateTimeBetween('-3 months', 'now');

        return [
            'user_id' => User::factory(),
            'area_id' => function (array $attributes) {
                return Area::factory()->create(['user_id' => $attributes['user_id']])->id;
            },
            'application_wave_id' => function (array $attributes) {
                return ApplicationWave::factory()->create(['user_id' => $attributes['user_id']])->id;
            },
            'position' => fake()->jobTitle(),
            'company' => fake()->company(),
            'location' => fake()->randomElement(['Remote', 'Hybrid', 'On-site']),
            'applied_at' => $appliedAt,
            'status' => $status,
            'channel' => fake()->optional()->randomElement(['Email', 'LinkedIn', 'Company website']),
            'notes' => fake()->optional()->sentence(),
            'job_url' => fake()->optional()->url(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Application $application) {
            if (! $application->applied_at) {
                return;
            }

            if (fake()->boolean(30)) {
                $application->moments()->create([
                    'type' => ApplicationMomentType::Interview,
                    'occurred_at' => fake()->dateTimeBetween($application->applied_at, 'now'),
                    'sort_order' => 0,
                ]);
            }

            if (in_array($application->status, [ApplicationStatus::Rejected, ApplicationStatus::Cancelled], true)) {
                $application->moments()->create([
                    'type' => ApplicationMomentType::Rejection,
                    'occurred_at' => fake()->dateTimeBetween($application->applied_at, 'now'),
                    'sort_order' => 1,
                ]);
            }
        });
    }
}
