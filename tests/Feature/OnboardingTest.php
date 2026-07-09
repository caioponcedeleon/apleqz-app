<?php

namespace Tests\Feature;

use App\Models\ApplicationWave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_waves_is_redirected_from_dashboard(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('waves.index'));
    }

    public function test_new_user_is_shown_onboarding(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('waves.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('onboarding.show', true)
            ->where('onboarding.hasWaves', false)
        );
    }

    public function test_user_with_waves_has_onboarding_has_waves_flag(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => null,
        ]);

        ApplicationWave::factory()->create([
            'user_id' => $user->id,
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->get(route('waves.index'))
            ->assertInertia(fn ($page) => $page
                ->where('onboarding.show', true)
                ->where('onboarding.hasWaves', true)
            );
    }

    public function test_creating_wave_updates_onboarding_has_waves_flag(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => null,
        ]);

        $this->actingAs($user)
            ->from(route('waves.index'))
            ->post(route('waves.store'), ['name' => 'Summer 2026'])
            ->assertRedirect(route('waves.index'));

        $this->actingAs($user)
            ->get(route('waves.index'))
            ->assertInertia(fn ($page) => $page
                ->where('onboarding.hasWaves', true)
                ->has('waves', 1)
            );
    }

    public function test_completing_onboarding_persists_and_hides_tour(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => null,
        ]);

        ApplicationWave::factory()->create([
            'user_id' => $user->id,
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('onboarding.complete'))
            ->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertNotNull($user->onboarding_completed_at);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('onboarding.show', false));
    }
}
