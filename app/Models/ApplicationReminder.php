<?php

namespace App\Models;

use App\Enums\ApplicationReminderFrequency;
use App\Enums\ApplicationReminderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationReminder extends Model
{
    protected $fillable = [
        'user_id',
        'application_id',
        'application_moment_id',
        'type',
        'frequency',
        'remind_at',
        'custom_message',
        'sent_at',
        'last_sent_at',
        'is_active',
        'channel',
    ];

    protected function casts(): array
    {
        return [
            'type' => ApplicationReminderType::class,
            'frequency' => ApplicationReminderFrequency::class,
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function moment(): BelongsTo
    {
        return $this->belongsTo(ApplicationMoment::class, 'application_moment_id');
    }

    public function isRecurring(): bool
    {
        return $this->frequency !== ApplicationReminderFrequency::Once;
    }
}
