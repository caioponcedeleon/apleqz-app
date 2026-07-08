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

class ApplicationMomentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_application_with_moments(): void
    {
        $user = User::factory()->create();
        $area = Area::factory()->create(['user_id' => $user->id]);
        $wave = ApplicationWave::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('applications.store'), [
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
            'position' => 'Engineer',
            'company' => 'Acme',
            'applied_at' => '2026-05-01',
            'status' => ApplicationStatus::Waiting->value,
            'moments' => [
                [
                    'type' => ApplicationMomentType::Interview->value,
                    'occurred_at' => '2026-05-10',
                    'notes' => 'Technical round',
                ],
                [
                    'type' => ApplicationMomentType::Rejection->value,
                    'occurred_at' => '2026-05-15',
                    'notes' => null,
                ],
            ],
        ]);

        $response->assertRedirect(route('applications.edit', Application::query()->first()));

        $application = Application::query()->first();
        $this->assertCount(2, $application->moments);
        $this->assertTrue(
            $application->moments->contains(
                fn ($moment) => $moment->type === ApplicationMomentType::Rejection
            )
        );
    }

    public function test_updating_application_replaces_moments(): void
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
        $moment = $application->moments()->create([
            'type' => ApplicationMomentType::Interview,
            'occurred_at' => '2026-05-05',
            'sort_order' => 0,
        ]);

        $this->actingAs($user)->put(route('applications.update', $application), [
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
            'position' => $application->position,
            'company' => $application->company,
            'applied_at' => '2026-05-01',
            'status' => ApplicationStatus::Waiting->value,
            'moments' => [
                [
                    'id' => $moment->id,
                    'type' => ApplicationMomentType::Offer->value,
                    'occurred_at' => '2026-05-20',
                    'notes' => 'Signed',
                ],
            ],
        ])->assertRedirect(route('applications.index'));

        $application->refresh();
        $this->assertCount(1, $application->moments);
        $this->assertSame(ApplicationMomentType::Offer, $application->moments->first()->type);
    }
}
