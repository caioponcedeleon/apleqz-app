<?php

namespace App\Models;

use App\Enums\JobAlertsTier;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'application_files_enabled',
        'personal_files_enabled',
        'excel_import_enabled',
        'job_alerts_tier',
        'locale',
        'email_reminders_enabled',
        'current_wave_id',
        'onboarding_completed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'application_files_enabled' => 'boolean',
            'personal_files_enabled' => 'boolean',
            'excel_import_enabled' => 'boolean',
            'email_reminders_enabled' => 'boolean',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function jobAlertsTier(): JobAlertsTier
    {
        $tier = $this->job_alerts_tier ?? JobAlertsTier::None->value;

        return JobAlertsTier::tryFrom($tier) ?? JobAlertsTier::None;
    }

    public function hasJobAlerts(): bool
    {
        return $this->jobAlertsTier() !== JobAlertsTier::None;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function applicationWaves(): HasMany
    {
        return $this->hasMany(ApplicationWave::class);
    }

    public function currentWave(): BelongsTo
    {
        return $this->belongsTo(ApplicationWave::class, 'current_wave_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(UserFile::class);
    }

    public function jobProfile(): HasOne
    {
        return $this->hasOne(UserJobProfile::class);
    }

    public function jobSourceSubscriptions(): HasMany
    {
        return $this->hasMany(UserJobSourceSubscription::class);
    }

    public function jobMatches(): HasMany
    {
        return $this->hasMany(JobMatch::class);
    }
}
