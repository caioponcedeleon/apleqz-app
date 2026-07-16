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
        $listing = JobListing::query()->with('jobSource')->find($this->listingId);

        if (! $listing) {
            return;
        }

        $shouldRematch = false;

        try {
            $shouldRematch = $enrichment->enrich($listing);
        } catch (\Throwable $exception) {
            Log::warning('Job listing detail enrichment failed', [
                'listing_id' => $listing->id,
                'message' => $exception->getMessage(),
            ]);

            // Mark enriched so evaluation is not deferred forever, then rematch
            // with whatever listing text we already have.
            if ($listing->detail_enriched_at === null) {
                $listing->update(['detail_enriched_at' => now()]);
            }

            $shouldRematch = true;
        }

        // Enrichment returned false without marking the listing (empty URL, etc.).
        // Unblock any matches that were deferred waiting on detail.
        if (! $shouldRematch && $listing->detail_enriched_at === null && $listing->jobSource) {
            $detailConfig = $enrichment->detailConfigFor($listing->jobSource);

            if ($detailConfig !== null) {
                $listing->update(['detail_enriched_at' => now()]);
                $shouldRematch = true;
            }
        }

        if ($shouldRematch) {
            MatchNewListingsJob::dispatch([$listing->id]);
        }
    }
}
