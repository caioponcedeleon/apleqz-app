<?php

namespace Tests\Feature;

use App\Enums\ApplicationMomentType;
use App\Enums\ApplicationReminderFrequency;
use App\Enums\ApplicationReminderType;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationReminder;
use App\Models\ApplicationWave;
use App\Models\Area;
use App\Models\User;
use App\Services\ApplicationReminderDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ApplicationReminderNotification;
use Tests\TestCase;

class ApplicationMomentsTest extends TestCase
{
    use RefreshDatabase;

    protected function applicationFor(User $user): Application
    {
        $area = Area::factory()->create(['user_id' => $user->id]);
        $wave = ApplicationWave::factory()->create(['user_id' => $user->id]);

        return Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
            'status' => ApplicationStatus::Waiting,
            'applied_at' => '2026-05-01',
        ]);
    }

    public function test_user_can_create_moment_on_application(): void
    {
        $user = User::factory()->create();
        $application = $this->applicationFor($user);

        $this->actingAs($user)
            ->post(route('applications.moments.store', $application), [
                'type' => ApplicationMomentType::Interview->value,
                'occurred_at' => '2026-05-10',
                'notes' => 'Technical round',
            ])
            ->assertRedirect();

        $this->assertTrue(
            $application->fresh()->moments->contains(
                fn ($moment) => $moment->type === ApplicationMomentType::Interview
                    && ! $moment->is_system
            )
        );
    }

    public function test_user_can_update_moment_on_application(): void
    {
        $user = User::factory()->create();
        $application = $this->applicationFor($user);
        $application->moments()->delete();
        $moment = $application->moments()->create([
            'type' => ApplicationMomentType::Interview,
            'occurred_at' => '2026-05-05',
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->patch(route('applications.moments.update', [$application, $moment]), [
                'type' => ApplicationMomentType::Offer->value,
                'occurred_at' => '2026-05-20',
                'notes' => 'Signed',
            ])
            ->assertRedirect();

        $moment->refresh();
        $this->assertSame(ApplicationMomentType::Offer, $moment->type);
        $this->assertSame('Signed', $moment->notes);
    }
}
