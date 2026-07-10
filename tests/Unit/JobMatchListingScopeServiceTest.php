<?php

namespace Tests\Unit;

use App\Models\JobListing;
use App\Models\JobSource;
use App\Services\JobMatchListingScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobMatchListingScopeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_most_recent_listings_per_source_up_to_limit(): void
    {
        config(['job_match.regex.recent_listings_per_source' => 5]);

        $source = JobSource::factory()->create();
        $otherSource = JobSource::factory()->create();

        $listings = collect(range(1, 7))->map(function (int $index) use ($source) {
            return JobListing::factory()->create([
                'job_source_id' => $source->id,
                'title' => "Role {$index}",
                'first_seen_at' => now()->subDays(10 - $index),
            ]);
        });

        JobListing::factory()->create([
            'job_source_id' => $otherSource->id,
            'first_seen_at' => now(),
        ]);

        $ids = app(JobMatchListingScopeService::class)->recentListingIdsForSources([$source->id]);

        $this->assertCount(5, $ids);
        $this->assertEqualsCanonicalizing(
            $listings->sortByDesc('first_seen_at')->take(5)->pluck('id')->all(),
            $ids,
        );
    }
}
