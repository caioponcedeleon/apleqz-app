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

    /**
     * Default list ordering: waiting to apply first, offers next, rejections last.
     */
    public function listSortPriority(): int
    {
        return match ($this) {
            self::WaitingToApply => 1,
            self::Offer => 2,
            self::Waiting => 3,
            self::Withdrawn => 4,
            self::Cancelled => 5,
            self::Rejected => 6,
            self::DeclinedByMe => 7,
        };
    }

    public static function listSortOrderSql(string $direction = 'asc'): string
    {
        $cases = collect(self::cases())
            ->map(fn (self $status) => "WHEN '{$status->value}' THEN {$status->listSortPriority()}")
            ->implode(' ');

        $caseExpression = "CASE status {$cases} ELSE 99 END";

        return $direction === 'desc'
            ? "{$caseExpression} DESC"
            : "{$caseExpression} ASC";
    }
}
