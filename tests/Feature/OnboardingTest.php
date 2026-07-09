<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_is_shown_onboarding(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('onboarding.show', true)
            ->where('onboarding.manageApplicationId', null)
        );
    }

    public function test_completing_onboarding_persists_and_hides_tour(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => null,
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
