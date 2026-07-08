<?php

namespace Database\Factories;

use App\Models\ApplicationWave;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationWave>
 */
class ApplicationWaveFactory extends Factory
{
    protected $model = ApplicationWave::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'starts_at' => null,
            'ends_at' => null,
            'is_default' => false,
        ];
    }
}
