<?php

namespace App\Models;

use App\Enums\ApplicationMomentType;
use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'area_id',
        'application_wave_id',
        'position',
        'company',
        'location',
        'applied_at',
        'status',
        'is_favourite',
        'channel',
        'notes',
        'job_url',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'date',
            'status' => ApplicationStatus::class,
            'is_favourite' => 'boolean',
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

    public function wave(): BelongsTo
    {
        return $this->belongsTo(ApplicationWave::class, 'application_wave_id');
    }

    public function moments(): HasMany
    {
        return $this->hasMany(ApplicationMoment::class)->orderBy('occurred_at')->orderBy('sort_order');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ApplicationFile::class)->orderBy('created_at');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(ApplicationReminder::class)->orderByDesc('remind_at');
    }

    protected static function booted(): void
    {
        static::deleting(function (Application $application): void {
            $application->files()->each(fn (ApplicationFile $file) => $file->delete());
            $application->reminders()->delete();
        });
    }

    protected function daysAfterRejection(): Attribute
    {
        return Attribute::get(function (): ?int {
            if (! $this->applied_at) {
                return null;
            }

            $rejection = $this->moments
                ->where('type', ApplicationMomentType::Rejection)
                ->sortBy('occurred_at')
                ->first();

            if (! $rejection) {
                return null;
            }

            return (int) $this->applied_at->diffInDays($rejection->occurred_at);
        });
    }

    public function hasInterview(): bool
    {
        if ($this->relationLoaded('moments')) {
            return $this->moments->contains(
                fn (ApplicationMoment $moment) => $moment->type === ApplicationMomentType::Interview
            );
        }

        return $this->moments()->where('type', ApplicationMomentType::Interview)->exists();
    }

    public function interviewMoments()
    {
        return $this->moments->where('type', ApplicationMomentType::Interview);
    }
}
