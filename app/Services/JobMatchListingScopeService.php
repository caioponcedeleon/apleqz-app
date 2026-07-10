<?php

namespace App\Services;

use App\Models\JobListing;

class JobMatchListingScopeService
{
    public function recentPerSourceLimit(): int
    {
        return max(1, (int) config('job_match.regex.recent_listings_per_source', 5));
    }

    /**
     * @param  list<string>  $sourceIds
     * @param  list<string>  $alwaysInclude
     * @return list<string>
     */
    public function recentListingIdsForSources(array $sourceIds, ?int $limit = null, array $alwaysInclude = []): array
    {
        $limit = $limit ?? $this->recentPerSourceLimit();

        $ids = collect($alwaysInclude);

        foreach ($sourceIds as $sourceId) {
            $recent = JobListing::query()
                ->where('job_source_id', $sourceId)
                ->orderByDesc('first_seen_at')
                ->limit($limit)
                ->pluck('id');

            $ids = $ids->merge($recent);
        }

        return $ids->unique()->values()->all();
    }
}
