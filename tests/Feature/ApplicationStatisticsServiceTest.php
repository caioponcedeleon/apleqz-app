<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
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

        Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'status' => ApplicationStatus::Waiting,
            'applied_at' => '2026-01-10',
            'interview_date' => null,
        ]);

        Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'status' => ApplicationStatus::Rejected,
            'applied_at' => '2026-01-11',
            'rejected_at' => '2026-01-20',
            'interview_date' => '2026-01-15',
        ]);

        Application::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'status' => ApplicationStatus::Offer,
            'applied_at' => '2026-01-12',
            'interview_date' => '2026-01-18',
        ]);

        $stats = app(ApplicationStatisticsService::class)->forUser($user);

        $this->assertSame(3, $stats['summary']['total_applications']);
        $this->assertSame(1, $stats['summary']['total_rejections']);
        $this->assertSame(2, $stats['summary']['total_interviews']);
        $this->assertSame(1, $stats['summary']['total_offers']);
        $this->assertSame(1, $stats['summary']['total_waiting']);
    }

    public function test_user_cannot_access_another_users_application(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
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
