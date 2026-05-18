<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case WaitingToApply = 'a_candidatar';
    case Waiting = 'esperando';
    case Rejected = 'rejeitado';
    case Offer = 'oferta';
    case DeclinedByMe = 'recusado';
    case Withdrawn = 'retirada';
    case Cancelled = 'cancelada';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function requiresAppliedDate(): bool
    {
        return $this !== self::WaitingToApply;
    }

    public function requiresRejectionDate(): bool
    {
        return in_array($this, [self::Rejected, self::Cancelled], true);
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::WaitingToApply => 'sky',
            self::Waiting => 'amber',
            self::Rejected => 'red',
            self::Offer => 'emerald',
            self::DeclinedByMe => 'orange',
            self::Withdrawn => 'slate',
            self::Cancelled => 'zinc',
        };
    }
}
