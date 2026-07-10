<?php

namespace Tests\Feature;

use App\Enums\JobMatchStatus;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\User;
use App\Models\UserJobProfile;
use App\Notifications\JobMatchesDigestNotification;
use App\Services\JobDigestDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class JobDigestTest extends TestCase
{
    use RefreshDatabase;

    protected function userWithJobAlerts(bool $enabled = true): User
    {
        $user = User::factory()->withJobAlertsAi()->create();

        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'profile_text' => 'Remote Laravel developer',
            'min_fit_score' => 70,
            'job_alerts_enabled' => $enabled,
        ]);

        return $user;
    }

    public function test_command_sends_digest_for_pending_matches(): void
    {
        Notification::fake();

        $user = $this->userWithJobAlerts();
        $listing = JobListing::factory()->create([
            'title' => 'Backend Developer',
            'company' => 'Acme GmbH',
        ]);

        JobMatch::factory()->create([
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
            'fit_score' => 84,
            'reason' => 'Strong overlap.',
            'status' => JobMatchStatus::PendingNotify,
        ]);

        $sent = app(JobDigestDispatchService::class)->sendPendingDigests();

        $this->assertSame([$user->id], $sent);
        Notification::assertSentTo($user, JobMatchesDigestNotification::class);

        $this->assertDatabaseHas('job_matches', [
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
            'status' => JobMatchStatus::Notified->value,
        ]);

        $this->assertNotNull(JobMatch::query()->first()?->notified_at);
    }

    public function test_command_skips_when_user_disabled_job_alerts(): void
    {
        Notification::fake();

        $user = $this->userWithJobAlerts(enabled: false);
        JobMatch::factory()->create([
            'user_id' => $user->id,
            'status' => JobMatchStatus::PendingNotify,
        ]);

        $sent = app(JobDigestDispatchService::class)->sendPendingDigests();

        $this->assertSame([], $sent);
        Notification::assertNothingSent();
        $this->assertDatabaseHas('job_matches', [
            'user_id' => $user->id,
            'status' => JobMatchStatus::PendingNotify->value,
        ]);
    }

    public function test_command_skips_unverified_users(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'profile_text' => 'Developer',
            'job_alerts_enabled' => true,
        ]);

        JobMatch::factory()->create([
            'user_id' => $user->id,
            'status' => JobMatchStatus::PendingNotify,
        ]);

        $sent = app(JobDigestDispatchService::class)->sendPendingDigests();

        $this->assertSame([], $sent);
        Notification::assertNothingSent();
    }

    public function test_command_batches_multiple_matches_per_user(): void
    {
        Notification::fake();

        $user = $this->userWithJobAlerts();

        JobMatch::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => JobMatchStatus::PendingNotify,
        ]);

        $sent = app(JobDigestDispatchService::class)->sendPendingDigests();

        $this->assertSame([$user->id], $sent);
        Notification::assertSentTo($user, JobMatchesDigestNotification::class, function (JobMatchesDigestNotification $notification): bool {
            return $notification->matches->count() === 2;
        });
    }

    public function test_digest_email_includes_only_top_ten_matches_by_score(): void
    {
        Notification::fake();

        $user = $this->userWithJobAlerts();

        for ($score = 100; $score >= 89; $score--) {
            JobMatch::factory()->create([
                'user_id' => $user->id,
                'fit_score' => $score,
                'status' => JobMatchStatus::PendingNotify,
            ]);
        }

        app(JobDigestDispatchService::class)->sendPendingDigests();

        Notification::assertSentTo($user, JobMatchesDigestNotification::class, function (JobMatchesDigestNotification $notification): bool {
            if ($notification->matches->count() !== 10) {
                return false;
            }

            $scores = $notification->matches->pluck('fit_score')->all();

            return $scores === range(100, 91);
        });

        $this->assertSame(10, JobMatch::query()
            ->where('user_id', $user->id)
            ->where('status', JobMatchStatus::Notified)
            ->count());
        $this->assertSame(2, JobMatch::query()
            ->where('user_id', $user->id)
            ->where('status', JobMatchStatus::PendingNotify)
            ->count());
    }

    public function test_artisan_command_reports_sent_count(): void
    {
        Notification::fake();

        $user = $this->userWithJobAlerts();
        JobMatch::factory()->create([
            'user_id' => $user->id,
            'status' => JobMatchStatus::PendingNotify,
        ]);

        $this->artisan('jobs:send-digests')
            ->expectsOutput('Sent 1 digest(s).')
            ->assertSuccessful();
    }

    public function test_digest_email_uses_user_locale(): void
    {
        $user = User::factory()->create(['locale' => 'pt']);
        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'profile_text' => 'Desenvolvedor remoto',
            'job_alerts_enabled' => true,
        ]);

        $listing = JobListing::factory()->create([
            'title' => 'Analista de Políticas',
            'company' => 'ONG Exemplo',
            'url' => 'https://example.org/jobs/1',
        ]);

        $match = JobMatch::factory()->create([
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
            'fit_score' => 78,
            'reason' => 'Boa combinação com o setor público.',
            'status' => JobMatchStatus::PendingNotify,
        ]);

        $match->load('jobListing');
        app()->setLocale($user->locale);

        $mail = (new JobMatchesDigestNotification(collect([$match])))->toMail($user);
        $html = $mail->render()->toHtml();

        $this->assertStringContainsString('Olá', $html);
        $this->assertStringContainsString('Encontramos 1 vaga que combina com o seu perfil', $html);
        $this->assertStringContainsString('Analista de Políticas', $html);
        $this->assertStringContainsString('ONG Exemplo', $html);
        $this->assertStringContainsString('78/100', $html);
        $this->assertStringContainsString('Ver todas as correspondências', $html);
        $this->assertStringNotContainsString('| Vaga |', $html);
        $this->assertStringContainsString(url('/images/logo.svg'), $html);
        $this->assertStringContainsString(route('job-alerts.matches'), $html);
        $this->assertStringNotContainsString('View all matches', $html);
    }
}
