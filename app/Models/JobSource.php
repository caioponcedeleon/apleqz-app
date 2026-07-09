<?php

namespace App\Models;

use App\Enums\JobExtractionEngine;
use App\Enums\JobScrapeStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobSource extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'url',
        'company_name',
        'is_active',
        'extraction_config',
        'config_version',
        'last_scraped_at',
        'last_scrape_status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'extraction_config' => 'array',
            'config_version' => 'integer',
            'last_scraped_at' => 'datetime',
            'last_scrape_status' => JobScrapeStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (JobSource $source): void {
            if ($source->extraction_config === null) {
                $source->extraction_config = self::defaultExtractionConfig();
            }

            if ($source->config_version === null) {
                $source->config_version = 1;
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultExtractionConfig(): array
    {
        return [
            'version' => 1,
            'engine' => JobExtractionEngine::Http->value,
            'sample_url' => null,
            'respect_robots' => false,
            'interactions' => [],
            'listing' => [
                'item_selector' => '',
                'fields' => [],
            ],
            'detail' => null,
            'pagination' => ['type' => 'none'],
        ];
    }

    public function scrapeRuns(): HasMany
    {
        return $this->hasMany(JobSourceScrapeRun::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(JobListing::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserJobSourceSubscription::class);
    }

    public function configRevisions(): HasMany
    {
        return $this->hasMany(JobSourceConfigRevision::class);
    }
}
