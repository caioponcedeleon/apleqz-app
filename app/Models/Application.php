<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'area_id',
        'position',
        'company',
        'location',
        'applied_at',
        'status',
        'rejected_at',
        'interview_date',
        'channel',
        'notes',
        'job_url',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'date',
            'rejected_at' => 'date',
            'interview_date' => 'date',
            'status' => ApplicationStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    protected function daysAfterRejection(): Attribute
    {
        return Attribute::get(function (): ?int {
            if (! $this->rejected_at || ! $this->applied_at) {
                return null;
            }

            $status = $this->status instanceof ApplicationStatus
                ? $this->status
                : ApplicationStatus::tryFrom((string) $this->status);

            $shouldShow = $status?->requiresRejectionDate()
                || $status === ApplicationStatus::Rejected
                || $status === ApplicationStatus::Cancelled;

            if (! $shouldShow && $this->rejected_at === null) {
                return null;
            }

            return (int) $this->applied_at->diffInDays($this->rejected_at);
        });
    }

    public function hasInterview(): bool
    {
        return $this->interview_date !== null;
    }
}
