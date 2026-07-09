<?php

namespace Tests\Feature;

use App\Enums\ApplicationMomentType;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationWave;
use App\Models\Area;
use App\Models\User;
use App\Services\ApplicationStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationStatisticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_counts_match_fixture_data(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $area = Area::factory()->create(['user_id' => $user->id, 'name' => 'Tech']);

        $waiting = Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'status' => ApplicationStatus::Waiting,
            'applied_at' => '2026-01-10',
        ]);
        $waiting->moments()->delete();

        $rejected = Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'status' => ApplicationStatus::Rejected,
            'applied_at' => '2026-01-11',
        ]);
        $rejected->moments()->delete();
        $rejected->moments()->create([
            'type' => ApplicationMomentType::Interview,
            'occurred_at' => '2026-01-15',
            'sort_order' => 0,
        ]);
        $rejected->moments()->create([
            'type' => ApplicationMomentType::Rejection,
            'occurred_at' => '2026-01-20',
            'sort_order' => 1,
        ]);

        $offer = Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'status' => ApplicationStatus::Offer,
            'applied_at' => '2026-01-12',
        ]);
        $offer->moments()->delete();
        $offer->moments()->create([
            'type' => ApplicationMomentType::Interview,
            'occurred_at' => '2026-01-18',
            'sort_order' => 0,
        ]);

        $stats = app(ApplicationStatisticsService::class)->forUser($user);

        $this->assertSame(3, $stats['summary']['total_applications']);
        $this->assertSame(1, $stats['summary']['total_rejections']);
        $this->assertSame(2, $stats['summary']['total_interviews']);
        $this->assertSame(1, $stats['summary']['total_offers']);
        $this->assertSame(1, $stats['summary']['total_waiting']);
        $this->assertEqualsWithDelta(0.3333, $stats['summary']['pct_rejections'], 0.0001);
        $this->assertEqualsWithDelta(0.6667, $stats['summary']['pct_interviews'], 0.0001);
        $this->assertEqualsWithDelta(0.3333, $stats['summary']['pct_offers'], 0.0001);
        $this->assertEqualsWithDelta(0.3333, $stats['summary']['pct_waiting'], 0.0001);
    }

    public function test_interview_percentage_counts_applications_not_moments(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $area = Area::factory()->create(['user_id' => $user->id]);

        $withTwoInterviews = Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'status' => ApplicationStatus::Waiting,
        ]);
        $withTwoInterviews->moments()->delete();
        $withTwoInterviews->moments()->create([
            'type' => ApplicationMomentType::Interview,
            'occurred_at' => '2026-01-10',
            'sort_order' => 0,
        ]);
        $withTwoInterviews->moments()->create([
            'type' => ApplicationMomentType::Interview,
            'occurred_at' => '2026-01-15',
            'sort_order' => 1,
        ]);

        Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'status' => ApplicationStatus::Waiting,
        ])->moments()->delete();

        $stats = app(ApplicationStatisticsService::class)->forUser($user);

        $this->assertSame(2, $stats['summary']['total_applications']);
        $this->assertSame(1, $stats['summary']['total_interviews']);
        $this->assertEqualsWithDelta(0.5, $stats['summary']['pct_interviews'], 0.0001);
    }

    public function test_user_cannot_access_another_users_application(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        ApplicationWave::factory()->create(['user_id' => $other->id]);
        $area = Area::factory()->create(['user_id' => $owner->id]);

        $application = Application::factory()->create([
            'user_id' => $owner->id,
            'area_id' => $area->id,
        ]);

        $this->actingAs($other)
            ->get(route('applications.edit', $application))
            ->assertForbidden();
    }
}
