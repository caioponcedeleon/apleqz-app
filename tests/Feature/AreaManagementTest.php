<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_areas_page(): void
    {
        $user = User::factory()->create();
        Area::factory()->create(['user_id' => $user->id, 'name' => 'Backend']);

        $response = $this->actingAs($user)->get(route('areas.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Areas/Index')
            ->has('areas', 1)
            ->where('areas.0.name', 'Backend'));
    }

    public function test_user_can_create_area_from_areas_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('areas.store'), [
            'name' => 'Frontend',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('areas', [
            'user_id' => $user->id,
            'name' => 'Frontend',
        ]);
    }
}
