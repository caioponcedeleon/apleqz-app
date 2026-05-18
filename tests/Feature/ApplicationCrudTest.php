<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_application(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $area = Area::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('applications.store'), [
                'area_id' => $area->id,
                'position' => 'Developer',
                'company' => 'Acme',
                'location' => 'Remote',
                'applied_at' => '2026-05-01',
                'status' => ApplicationStatus::Waiting->value,
            ])
            ->assertRedirect(route('applications.index'));

        $this->assertDatabaseHas('applications', [
            'user_id' => $user->id,
            'position' => 'Developer',
            'company' => 'Acme',
        ]);
    }

    public function test_application_search_is_case_insensitive(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $area = Area::factory()->create(['user_id' => $user->id]);

        $user->applications()->create([
            'area_id' => $area->id,
            'position' => 'Senior Backend Engineer',
            'company' => 'Globex Corporation',
            'applied_at' => '2026-05-01',
            'status' => ApplicationStatus::Waiting,
        ]);

        $this->actingAs($user)
            ->get(route('applications.index', ['search' => 'backend']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('applications.data', 1)
                ->where('applications.data.0.position', 'Senior Backend Engineer'));

        $this->actingAs($user)
            ->get(route('applications.index', ['search' => 'GLOBEX']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('applications.data', 1));
    }
}
