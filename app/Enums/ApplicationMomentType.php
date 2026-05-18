<?php

namespace App\Enums;

enum ApplicationMomentType: string
{
    case Feedback = 'feedback';
    case Interview = 'interview';
    case Offer = 'offer';
    case Rejection = 'rejection';
    case Other = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
