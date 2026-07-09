<?php

namespace Tests\Feature;

use App\Enums\ApplicationMomentType;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationWave;
use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_application_records_initial_status_event(): void
    {
        $user = User::factory()->create();
        $area = Area::factory()->create(['user_id' => $user->id]);
        $wave = ApplicationWave::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('applications.store'), [
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
            'position' => 'Engineer',
            'company' => 'Acme',
            'applied_at' => '2026-05-01',
            'status' => ApplicationStatus::Waiting->value,
        ])->assertRedirect(route('applications.edit', Application::query()->first()));

        $application = Application::query()->first();

        $this->assertCount(1, $application->moments);
        $moment = $application->moments->first();
        $this->assertTrue($moment->is_system);
        $this->assertSame(ApplicationMomentType::StatusChange, $moment->type);
        $this->assertSame(ApplicationStatus::Waiting->value, $moment->notes);
        $this->assertSame('2026-05-01', $moment->occurred_at->toDateString());
    }

    public function test_updating_status_records_new_status_event(): void
    {
        $user = User::factory()->create();
        $area = Area::factory()->create(['user_id' => $user->id]);
        $wave = ApplicationWave::factory()->create(['user_id' => $user->id]);
        $application = Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
            'status' => ApplicationStatus::Waiting,
            'applied_at' => '2026-05-01',
        ]);
        $application->moments()->delete();
        $application->moments()->create([
            'type' => ApplicationMomentType::StatusChange,
            'occurred_at' => '2026-05-01',
            'notes' => ApplicationStatus::Waiting->value,
            'sort_order' => 0,
            'is_system' => true,
        ]);

        $this->actingAs($user)->put(route('applications.update', $application), [
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
            'position' => $application->position,
            'company' => $application->company,
            'applied_at' => '2026-05-01',
            'status' => ApplicationStatus::Rejected->value,
        ])->assertRedirect(route('applications.edit', $application));

        $application->refresh();

        $statusEvents = $application->moments()
            ->where('type', ApplicationMomentType::StatusChange)
            ->orderBy('occurred_at')
            ->get();

        $this->assertCount(2, $statusEvents);
        $this->assertSame(ApplicationStatus::Waiting->value, $statusEvents->first()->notes);
        $this->assertSame(ApplicationStatus::Rejected->value, $statusEvents->last()->notes);
    }

    public function test_system_status_events_are_not_removed_when_application_is_updated(): void
    {
        $user = User::factory()->create();
        $area = Area::factory()->create(['user_id' => $user->id]);
        $wave = ApplicationWave::factory()->create(['user_id' => $user->id]);
        $application = Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
            'status' => ApplicationStatus::Waiting,
            'applied_at' => '2026-05-01',
        ]);
        $application->moments()->delete();

        $statusEvent = $application->moments()->create([
            'type' => ApplicationMomentType::StatusChange,
            'occurred_at' => '2026-05-01',
            'notes' => ApplicationStatus::Waiting->value,
            'sort_order' => 0,
            'is_system' => true,
        ]);

        $this->actingAs($user)->put(route('applications.update', $application), [
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
            'position' => $application->position,
            'company' => $application->company,
            'applied_at' => '2026-05-01',
            'status' => ApplicationStatus::Waiting->value,
        ])->assertRedirect(route('applications.edit', $application));

        $this->actingAs($user)->post(route('applications.moments.store', $application), [
            'type' => ApplicationMomentType::Interview->value,
            'occurred_at' => '2026-05-10',
            'notes' => 'Technical round',
        ])->assertRedirect();

        $application->refresh();

        $this->assertTrue($application->moments()->whereKey($statusEvent->id)->exists());
        $this->assertCount(2, $application->moments);
    }
}
