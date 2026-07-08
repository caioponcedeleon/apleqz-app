<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\ApplicationWave;
use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_create_application_without_areas(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        ApplicationWave::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('applications.store'), [
                'area_id' => '00000000-0000-4000-8000-000000000001',
                'application_wave_id' => ApplicationWave::factory()->create(['user_id' => $user->id])->id,
                'position' => 'Developer',
                'company' => 'Acme',
                'applied_at' => '2026-05-01',
                'status' => ApplicationStatus::Waiting->value,
            ])
            ->assertRedirect(route('areas.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_user_cannot_create_application_without_waves(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $area = Area::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('applications.store'), [
                'area_id' => $area->id,
                'application_wave_id' => '00000000-0000-4000-8000-000000000001',
                'position' => 'Developer',
                'company' => 'Acme',
                'applied_at' => '2026-05-01',
                'status' => ApplicationStatus::Waiting->value,
            ])
            ->assertRedirect(route('waves.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_create_application_page_blocked_when_user_has_no_areas(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('applications.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Applications/Form')
                ->where('canCreateApplication', false)
                ->has('areas', 0));
    }

    public function test_user_can_create_application(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $area = Area::factory()->create(['user_id' => $user->id]);
        $wave = ApplicationWave::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('applications.store'), [
                'area_id' => $area->id,
                'application_wave_id' => $wave->id,
                'position' => 'Developer',
                'company' => 'Acme',
                'location' => 'Remote',
                'applied_at' => '2026-05-01',
                'status' => ApplicationStatus::Waiting->value,
            ])
            ->assertRedirect(route('applications.edit', \App\Models\Application::query()->first()));

        $this->assertDatabaseHas('applications', [
            'user_id' => $user->id,
            'position' => 'Developer',
            'company' => 'Acme',
            'application_wave_id' => $wave->id,
        ]);
    }

    public function test_application_search_is_case_insensitive(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $area = Area::factory()->create(['user_id' => $user->id]);
        $wave = ApplicationWave::factory()->create(['user_id' => $user->id]);

        $user->applications()->create([
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
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

    public function test_applications_default_sort_is_by_status_priority(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $area = Area::factory()->create(['user_id' => $user->id]);
        $wave = ApplicationWave::factory()->create(['user_id' => $user->id]);

        $user->applications()->create([
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
            'position' => 'Rejected role',
            'company' => 'Acme',
            'applied_at' => '2026-06-01',
            'status' => ApplicationStatus::Rejected,
        ]);

        $user->applications()->create([
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
            'position' => 'Offer role',
            'company' => 'Acme',
            'applied_at' => '2026-01-01',
            'status' => ApplicationStatus::Offer,
            'is_favourite' => true,
        ]);

        $user->applications()->create([
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
            'position' => 'Waiting role',
            'company' => 'Acme',
            'applied_at' => '2026-03-01',
            'status' => ApplicationStatus::Waiting,
        ]);

        $user->applications()->create([
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
            'position' => 'To apply role',
            'company' => 'Acme',
            'applied_at' => null,
            'status' => ApplicationStatus::WaitingToApply,
        ]);

        $this->actingAs($user)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.sort', 'status')
                ->where('filters.direction', 'asc')
                ->where('applications.data.0.position', 'Offer role')
                ->where('applications.data.1.position', 'To apply role')
                ->where('applications.data.2.position', 'Waiting role')
                ->where('applications.data.3.position', 'Rejected role'));

        $this->actingAs($user)
            ->get(route('applications.index', ['sort' => 'applied_at', 'direction' => 'desc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('applications.data.0.position', 'Rejected role')
                ->where('applications.data.1.position', 'Waiting role'));
    }

    public function test_user_can_toggle_application_favourite(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $application = \App\Models\Application::factory()->for($user)->create([
            'is_favourite' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('applications.favourite', $application))
            ->assertRedirect();

        $this->assertTrue($application->fresh()->is_favourite);

        $this->actingAs($user)
            ->patch(route('applications.favourite', $application))
            ->assertRedirect();

        $this->assertFalse($application->fresh()->is_favourite);
    }
}
