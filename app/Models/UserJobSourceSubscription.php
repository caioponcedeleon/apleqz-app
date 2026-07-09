<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserJobSourceSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'job_source_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobSource(): BelongsTo
    {
        return $this->belongsTo(JobSource::class);
    }
}
