<?php

namespace Tests\Unit;

use App\Models\Application;
use App\Models\JobListing;
use App\Models\User;
use App\Enums\ApplicationStatus;
use App\Services\JobMatchApplicationOverlapChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobMatchApplicationOverlapCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizes_position_and_url_for_comparison(): void
    {
        $this->assertSame('senior engineer', JobMatchApplicationOverlapChecker::normalizePosition('  Senior   Engineer '));
        $this->assertSame('https://example.com/jobs/1', JobMatchApplicationOverlapChecker::normalizeUrl('HTTPS://Example.com/jobs/1/'));
    }

    public function test_detects_overlap_by_position_or_url(): void
    {
        $user = User::factory()->create();
        Application::factory()->create([
            'user_id' => $user->id,
            'position' => 'Policy Analyst',
            'job_url' => null,
            'applied_at' => now()->subDay(),
            'status' => ApplicationStatus::Waiting,
        ]);

        $checker = app(JobMatchApplicationOverlapChecker::class);

        $byTitle = JobListing::factory()->make([
            'title' => 'policy analyst',
            'url' => 'https://example.com/other',
        ]);
        $byUrl = JobListing::factory()->make([
            'title' => 'Different role',
            'url' => 'https://example.com/jobs/policy',
        ]);
        $unrelated = JobListing::factory()->make([
            'title' => 'Engineer',
            'url' => 'https://example.com/engineer',
        ]);

        Application::factory()->create([
            'user_id' => $user->id,
            'position' => 'Other role',
            'job_url' => 'https://example.com/jobs/policy/',
            'applied_at' => now()->subDay(),
            'status' => ApplicationStatus::Waiting,
        ]);

        $this->assertTrue($checker->overlapsExistingApplication($user->id, $byTitle));
        $this->assertTrue($checker->overlapsExistingApplication($user->id, $byUrl));
        $this->assertFalse($checker->overlapsExistingApplication($user->id, $unrelated));
    }
}
