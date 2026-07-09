<?php

namespace App\Enums;

enum ApplicationReminderFrequency: string
{
    case Once = 'once';
    case Daily = 'daily';
    case Weekly = 'weekly';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
