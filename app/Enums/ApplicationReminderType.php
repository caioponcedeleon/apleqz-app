<?php

namespace App\Enums;

enum ApplicationReminderType: string
{
    case CheckIn = 'check_in';
    case Moment = 'moment';
    case Custom = 'custom';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
