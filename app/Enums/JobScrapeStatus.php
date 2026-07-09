<?php

namespace App\Enums;

enum JobScrapeStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Partial = 'partial';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
