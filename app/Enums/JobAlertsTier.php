<?php

namespace App\Enums;

enum JobAlertsTier: string
{
    case None = 'none';
    case Regex = 'regex';
    case Ai = 'ai';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::None => __('app.job_alerts.tiers.none'),
            self::Regex => __('app.job_alerts.tiers.regex'),
            self::Ai => __('app.job_alerts.tiers.ai'),
        };
    }
}
