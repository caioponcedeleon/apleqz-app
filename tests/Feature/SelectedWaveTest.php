<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationWave;
use App\Models\Area;
use App\Models\User;
use App\Services\ApplicationStatisticsService;
use App\Services\SelectedWaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelectedWaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_applications_use_selected_wave(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $area = Area::factory()->create(['user_id' => $user->id]);

        $waveA = ApplicationWave::factory()->create([
            'user_id' => $user->id,
            'name' => 'Wave A',
            'is_default' => true,
        ]);
        $waveB = ApplicationWave::factory()->create([
            'user_id' => $user->id,
            'name' => 'Wave B',
        ]);

        Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'application_wave_id' => $waveA->id,
            'position' => 'In wave A',
        ]);
        Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'application_wave_id' => $waveB->id,
            'position' => 'In wave B',
        ]);

        $this->actingAs($user)
            ->post(route('wave.select'), ['wave_id' => $waveB->id])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame($waveB->id, $user->current_wave_id);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('selectedWave.id', $waveB->id)
                ->where('statistics.summary.total_applications', 1));

        $this->actingAs($user)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.wave_id', $waveB->id)
                ->has('applications.data', 1)
                ->where('applications.data.0.position', 'In wave B'));
    }

    public function test_creating_wave_selects_it_automatically(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        ApplicationWave::factory()->create([
            'user_id' => $user->id,
            'name' => 'Existing wave',
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->post(route('waves.store'), ['name' => 'Summer search'])
            ->assertRedirect();

        $newWave = ApplicationWave::query()->where('name', 'Summer search')->first();

        $this->assertNotNull($newWave);
        $this->assertSame($newWave->id, $user->fresh()->current_wave_id);
    }

    public function test_statistics_service_filters_by_wave(): void
    {
        $user = User::factory()->create();
        $area = Area::factory()->create(['user_id' => $user->id]);
        $waveA = ApplicationWave::factory()->create(['user_id' => $user->id]);
        $waveB = ApplicationWave::factory()->create(['user_id' => $user->id]);

        Application::factory()->count(2)->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'application_wave_id' => $waveA->id,
        ]);
        Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'application_wave_id' => $waveB->id,
        ]);

        $stats = app(ApplicationStatisticsService::class)->forUser($user, $waveA->id);

        $this->assertSame(2, $stats['summary']['total_applications']);
    }

    public function test_selected_wave_service_falls_back_to_default_wave(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $default = ApplicationWave::factory()->create([
            'user_id' => $user->id,
            'is_default' => true,
        ]);
        ApplicationWave::factory()->create(['user_id' => $user->id, 'is_default' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('selectedWave.id', $default->id));
    }
}
