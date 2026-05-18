<?php

namespace App\Models;

use App\Enums\ApplicationMomentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationMoment extends Model
{
    protected $fillable = [
        'application_id',
        'type',
        'occurred_at',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => ApplicationMomentType::class,
            'occurred_at' => 'date',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
