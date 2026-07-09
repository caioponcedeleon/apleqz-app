<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSourceConfigRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'job_source_id',
        'config_version',
        'extraction_config',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'config_version' => 'integer',
            'extraction_config' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function jobSource(): BelongsTo
    {
        return $this->belongsTo(JobSource::class);
    }
}
