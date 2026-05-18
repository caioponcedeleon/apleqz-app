<?php

namespace App\Console\Commands;

use App\Exceptions\ApplicationImportException;
use App\Models\User;
use App\Services\ApplicationImportService;
use Illuminate\Console\Command;

class ImportApplicationsFromExcel extends Command
{
    protected $signature = 'applications:import
                            {path : Path to the Excel file}
                            {--user= : Email of the user to assign rows to}';

    protected $description = 'Import job applications from an Excel spreadsheet (Vagas sheet)';

    public function handle(ApplicationImportService $importService): int
    {
        $user = $this->resolveUser();

        if (! $user) {
            return self::FAILURE;
        }

        try {
            $result = $importService->importFromPath($user, $this->argument('path'));
        } catch (ApplicationImportException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$result->imported} applications for {$user->email}. Skipped {$result->skipped} rows.");

        return self::SUCCESS;
    }

    protected function resolveUser(): ?User
    {
        $email = $this->option('user');

        if (! $email) {
            $this->error('Please provide --user=email');

            return null;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("User not found: {$email}");
        }

        return $user;
    }
}
