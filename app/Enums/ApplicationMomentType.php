<?php

namespace App\Enums;

enum ApplicationMomentType: string
{
    case Feedback = 'feedback';
    case Interview = 'interview';
    case Offer = 'offer';
    case Rejection = 'rejection';
    case StatusChange = 'status_change';
    case Other = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Moment types the user can add or edit in the application form.
     *
     * @return list<string>
     */
    public static function userEditableValues(): array
    {
        return array_values(array_filter(
            self::values(),
            fn (string $value) => $value !== self::StatusChange->value,
        ));
    }
}
