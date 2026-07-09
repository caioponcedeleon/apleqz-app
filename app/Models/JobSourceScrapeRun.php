<?php

namespace App\Models;

use App\Enums\JobScrapeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSourceScrapeRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_source_id',
        'started_at',
        'finished_at',
        'status',
        'listings_found',
        'listings_new',
        'error_message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'status' => JobScrapeStatus::class,
            'listings_found' => 'integer',
            'listings_new' => 'integer',
            'meta' => 'array',
        ];
    }

    public function jobSource(): BelongsTo
    {
        return $this->belongsTo(JobSource::class);
    }
}
