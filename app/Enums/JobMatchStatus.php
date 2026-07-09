<?php

namespace App\Enums;

enum JobMatchStatus: string
{
    case PendingNotify = 'pending_notify';
    case Notified = 'notified';
    case Dismissed = 'dismissed';
    case Applied = 'applied';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
