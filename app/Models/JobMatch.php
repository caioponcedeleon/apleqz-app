<?php

namespace App\Models;

use App\Enums\JobMatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobMatch extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'job_listing_id',
        'fit_score',
        'reason',
        'status',
        'evaluation_cache_key',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'fit_score' => 'integer',
            'status' => JobMatchStatus::class,
            'notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobListing(): BelongsTo
    {
        return $this->belongsTo(JobListing::class);
    }
}
