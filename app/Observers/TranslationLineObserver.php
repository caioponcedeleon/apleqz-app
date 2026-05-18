<?php

namespace App\Observers;

use App\Models\TranslationLine;
use App\Services\TranslationService;

class TranslationLineObserver
{
    public function saved(TranslationLine $line): void
    {
        app(TranslationService::class)->flush($line->locale);
    }

    public function deleted(TranslationLine $line): void
    {
        app(TranslationService::class)->flush($line->locale);
    }
}
