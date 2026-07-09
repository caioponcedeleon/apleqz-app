<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobListing extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'job_source_id',
        'external_id',
        'title',
        'url',
        'company',
        'location',
        'salary',
        'application_deadline',
        'description',
        'raw_fields',
        'content_hash',
        'detail_enriched_at',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_fields' => 'array',
            'detail_enriched_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function jobSource(): BelongsTo
    {
        return $this->belongsTo(JobSource::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(JobMatch::class);
    }
}
