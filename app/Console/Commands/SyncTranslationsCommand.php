<?php

namespace App\Console\Commands;

use App\Services\TranslationService;
use Illuminate\Console\Command;

class SyncTranslationsCommand extends Command
{
    protected $signature = 'translations:sync {--locale= : Sync a single locale only}';

    protected $description = 'Import translation strings from lang files into the database and refresh the cache';

    public function handle(TranslationService $translations): int
    {
        $locale = $this->option('locale');

        if ($locale) {
            if (! in_array($locale, $translations->availableLocales(), true)) {
                $this->error("Unknown locale: {$locale}");

                return self::FAILURE;
            }

            $count = $translations->seedFromFiles($locale);
            $this->info("Synced {$count} lines for [{$locale}].");

            return self::SUCCESS;
        }

        $count = $translations->syncAllFromFiles();
        $this->info("Synced {$count} translation lines.");

        return self::SUCCESS;
    }
}
