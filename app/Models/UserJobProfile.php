<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserJobProfile extends Model
{
    public const DEFAULT_MIN_FIT_SCORE = 70;

    public const PROFILE_TEXT_MAX_LENGTH = 1000;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'profile_text',
        'min_fit_score',
        'job_alerts_enabled',
        'include_keywords',
        'exclude_keywords',
    ];

    protected function casts(): array
    {
        return [
            'min_fit_score' => 'integer',
            'job_alerts_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultsFor(User $user): array
    {
        return [
            'user_id' => $user->id,
            'profile_text' => '',
            'min_fit_score' => self::DEFAULT_MIN_FIT_SCORE,
            'job_alerts_enabled' => false,
            'include_keywords' => '',
            'exclude_keywords' => '',
        ];
    }
}
