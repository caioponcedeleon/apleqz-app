<?php

namespace Tests\Feature;

use App\Models\ApplicationWave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaveManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_waves_page(): void
    {
        $user = User::factory()->create();
        ApplicationWave::factory()->create(['user_id' => $user->id, 'name' => 'Spring 2026']);

        $response = $this->actingAs($user)->get(route('waves.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Waves/Index')
            ->has('waves', 1)
            ->where('waves.0.name', 'Spring 2026'));
    }

    public function test_user_can_create_wave_from_waves_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('waves.store'), [
            'name' => 'Summer 2026',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('application_waves', [
            'user_id' => $user->id,
            'name' => 'Summer 2026',
            'is_default' => true,
        ]);
    }

    public function test_user_can_set_default_wave(): void
    {
        $user = User::factory()->create();
        $default = ApplicationWave::factory()->create([
            'user_id' => $user->id,
            'name' => 'Spring 2026',
            'is_default' => true,
        ]);
        $other = ApplicationWave::factory()->create([
            'user_id' => $user->id,
            'name' => 'Summer 2026',
            'is_default' => false,
        ]);

        $this->actingAs($user)
            ->from(route('waves.index'))
            ->post(route('waves.default', $other))
            ->assertRedirect(route('waves.index'));

        $this->assertDatabaseHas('application_waves', [
            'id' => $other->id,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('application_waves', [
            'id' => $default->id,
            'is_default' => false,
        ]);

        $user->refresh();
        $this->assertSame($other->id, $user->current_wave_id);
    }
}
