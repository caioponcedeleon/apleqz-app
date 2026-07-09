<?php

namespace Tests\Feature;

use App\Enums\ApplicationReminderFrequency;
use App\Enums\ApplicationReminderType;
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

class ApplicationReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function applicationFor(User $user): Application
    {
        ApplicationWave::factory()->create(['user_id' => $user->id]);
        $area = Area::factory()->create(['user_id' => $user->id]);
        $wave = $user->applicationWaves()->first();

        return Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'application_wave_id' => $wave->id,
        ]);
    }

    public function test_user_can_create_reminder_on_application(): void
    {
        $user = User::factory()->create();
        $application = $this->applicationFor($user);

        $this->actingAs($user)
            ->post(route('applications.reminders.store', $application), [
                'type' => ApplicationReminderType::CheckIn->value,
                'frequency' => ApplicationReminderFrequency::Once->value,
                'remind_at' => now()->addDay()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('application_reminders', [
            'application_id' => $application->id,
            'user_id' => $user->id,
            'type' => ApplicationReminderType::CheckIn->value,
        ]);
    }

    public function test_command_sends_due_once_reminder_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_reminders_enabled' => true]);
        $application = $this->applicationFor($user);

        ApplicationReminder::query()->create([
            'user_id' => $user->id,
            'application_id' => $application->id,
            'type' => ApplicationReminderType::Custom,
            'frequency' => ApplicationReminderFrequency::Once,
            'remind_at' => now()->subMinute(),
            'custom_message' => 'Call the recruiter',
            'is_active' => true,
            'channel' => 'mail',
        ]);

        $sent = app(ApplicationReminderDispatchService::class)->sendDueReminders();

        $this->assertCount(1, $sent);
        Notification::assertSentTo($user, ApplicationReminderNotification::class);
    }

    public function test_command_skips_when_user_disabled_email_reminders(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_reminders_enabled' => false]);
        $application = $this->applicationFor($user);

        ApplicationReminder::query()->create([
            'user_id' => $user->id,
            'application_id' => $application->id,
            'type' => ApplicationReminderType::CheckIn,
            'frequency' => ApplicationReminderFrequency::Once,
            'remind_at' => now()->subMinute(),
            'is_active' => true,
            'channel' => 'mail',
        ]);

        $sent = app(ApplicationReminderDispatchService::class)->sendDueReminders();

        $this->assertSame([], $sent);
        Notification::assertNothingSent();
    }

    public function test_command_does_not_resend_once_reminder(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_reminders_enabled' => true]);
        $application = $this->applicationFor($user);

        ApplicationReminder::query()->create([
            'user_id' => $user->id,
            'application_id' => $application->id,
            'type' => ApplicationReminderType::CheckIn,
            'frequency' => ApplicationReminderFrequency::Once,
            'remind_at' => now()->subDay(),
            'sent_at' => now()->subHour(),
            'last_sent_at' => now()->subHour(),
            'is_active' => true,
            'channel' => 'mail',
        ]);

        $sent = app(ApplicationReminderDispatchService::class)->sendDueReminders();

        $this->assertSame([], $sent);
        Notification::assertNothingSent();
    }
}
