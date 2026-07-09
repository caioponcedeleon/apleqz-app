<?php

use App\Models\TranslationLine;
use App\Services\TranslationService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(TranslationService::class)->seedFromFiles('de');
    }

    public function down(): void
    {
        TranslationLine::query()->where('locale', 'de')->delete();

        app(TranslationService::class)->flush('de');
    }
};
