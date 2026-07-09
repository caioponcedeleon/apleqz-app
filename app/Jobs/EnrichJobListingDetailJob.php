<?php

namespace App\Jobs;

use App\Models\JobListing;
use App\Services\JobListingDetailEnrichmentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnrichJobListingDetailJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function __construct(
        public string $listingId,
    ) {}

    public function uniqueId(): string
    {
        return 'enrich-job-listing-'.$this->listingId;
    }

    public function handle(JobListingDetailEnrichmentService $enrichment): void
    {
        $listing = JobListing::query()->find($this->listingId);

        if (! $listing) {
            return;
        }

        try {
            if ($enrichment->enrich($listing)) {
                MatchNewListingsJob::dispatch([$listing->id]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Job listing detail enrichment failed', [
                'listing_id' => $listing->id,
                'message' => $exception->getMessage(),
            ]);

            $listing->update(['detail_enriched_at' => now()]);
        }
    }
}
