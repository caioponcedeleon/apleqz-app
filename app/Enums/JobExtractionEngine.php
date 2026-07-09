<?php

namespace App\Enums;

enum JobExtractionEngine: string
{
    case Http = 'http';
    case Playwright = 'playwright';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
