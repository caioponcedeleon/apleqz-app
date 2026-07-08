<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
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
        'locale',
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
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function applicationWaves(): HasMany
    {
        return $this->hasMany(ApplicationWave::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(UserFile::class);
    }
}
