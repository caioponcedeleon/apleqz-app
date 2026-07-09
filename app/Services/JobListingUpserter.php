<?php

namespace App\Services;

use App\Models\JobListing;
use App\Models\JobSource;
use Illuminate\Support\Carbon;

class JobListingUpserter
{
    /**
     * @param  list<array<string, mixed>>  $listings
     * @return array{found: int, new: int}
     */
    public function upsertMany(JobSource $source, array $listings, ?Carbon $seenAt = null): array
    {
        $seenAt ??= now();
        $found = 0;
        $new = 0;

        foreach ($listings as $listing) {
            $existing = $this->findExisting($source, $listing);

            if ($existing) {
                $existing->update([
                    'external_id' => $listing['external_id'],
                    'title' => $listing['title'],
                    'url' => $listing['url'],
                    'company' => $listing['company'],
                    'location' => $listing['location'],
                    'salary' => $listing['salary'],
                    'application_deadline' => $listing['application_deadline'],
                    'description' => $listing['description'],
                    'raw_fields' => $listing['raw_fields'],
                    'content_hash' => $listing['content_hash'],
                    'last_seen_at' => $seenAt,
                ]);
            } else {
                JobListing::query()->create([
                    'job_source_id' => $source->id,
                    'external_id' => $listing['external_id'],
                    'title' => $listing['title'],
                    'url' => $listing['url'],
                    'company' => $listing['company'],
                    'location' => $listing['location'],
                    'salary' => $listing['salary'],
                    'application_deadline' => $listing['application_deadline'],
                    'description' => $listing['description'],
                    'raw_fields' => $listing['raw_fields'],
                    'content_hash' => $listing['content_hash'],
                    'first_seen_at' => $seenAt,
                    'last_seen_at' => $seenAt,
                ]);
                $new++;
            }

            $found++;
        }

        return [
            'found' => $found,
            'new' => $new,
        ];
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    protected function findExisting(JobSource $source, array $listing): ?JobListing
    {
        $query = JobListing::query()->where('job_source_id', $source->id);

        $externalId = $listing['external_id'] ?? null;

        if (is_string($externalId) && $externalId !== '') {
            $match = (clone $query)->where('external_id', $externalId)->first();

            if ($match) {
                return $match;
            }
        }

        $url = $listing['url'] ?? null;

        if (is_string($url) && trim($url) !== '') {
            $match = (clone $query)->where('url', trim($url))->first();

            if ($match) {
                return $match;
            }
        }

        $title = $listing['title'] ?? null;
        $company = $listing['company'] ?? null;

        if (is_string($title) && trim($title) !== '' && is_string($company) && trim($company) !== '') {
            return (clone $query)
                ->where('title', trim($title))
                ->where('company', trim($company))
                ->first();
        }

        return null;
    }
}
