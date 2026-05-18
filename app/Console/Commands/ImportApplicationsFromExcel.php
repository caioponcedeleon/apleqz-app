<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Area;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use OpenSpout\Reader\XLSX\Reader;

class ImportApplicationsFromExcel extends Command
{
    protected $signature = 'applications:import
                            {path : Path to the Excel file}
                            {--user= : Email of the user to assign rows to}';

    protected $description = 'Import job applications from an Excel spreadsheet (Vagas sheet)';

    protected array $statusMap = [
        'esperando' => ApplicationStatus::Waiting,
        'rejeitado' => ApplicationStatus::Rejected,
        'oferta' => ApplicationStatus::Offer,
        'recusado' => ApplicationStatus::DeclinedByMe,
        'retirada' => ApplicationStatus::Withdrawn,
        'cancelada' => ApplicationStatus::Cancelled,
    ];

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $user = $this->resolveUser();

        if (! $user) {
            return self::FAILURE;
        }

        $reader = new Reader;
        $reader->open($path);

        $imported = 0;
        $skipped = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== 'Vagas') {
                continue;
            }

            $isHeader = true;

            foreach ($sheet->getRowIterator() as $row) {
                if ($isHeader) {
                    $isHeader = false;

                    continue;
                }

                $cells = $row->getCells();
                $values = array_map(fn ($cell) => $cell->getValue(), iterator_to_array($cells));

                if (count($values) < 6 || empty($values[0])) {
                    $skipped++;

                    continue;
                }

                $areaName = trim((string) ($values[1] ?? ''));
                $area = $this->resolveArea($user, $areaName);

                $appliedAt = $this->parseDate($values[4] ?? null);
                $status = $this->parseStatus($values[5] ?? null);

                if (! $appliedAt || ! $status) {
                    $skipped++;
                    $this->warn('Skipped row: invalid date or status — '.($values[0] ?? ''));

                    continue;
                }

                $rejectedAt = $this->parseDate($values[6] ?? null);
                $interviewDate = $this->parseDate($values[8] ?? null);
                $channel = $this->stringOrNull($values[9] ?? null);
                $notes = $this->stringOrNull($values[10] ?? null);

                if (! $interviewDate && isset($values[8]) && is_string($values[8]) && $values[8] !== '') {
                    $notes = trim(($notes ? $notes.' ' : '').'Interview note: '.$values[8]);
                }

                Application::query()->create([
                    'user_id' => $user->id,
                    'area_id' => $area->id,
                    'position' => trim((string) $values[0]),
                    'company' => trim((string) ($values[2] ?? '')),
                    'location' => $this->stringOrNull($values[3] ?? null),
                    'applied_at' => $appliedAt,
                    'status' => $status,
                    'rejected_at' => $rejectedAt,
                    'interview_date' => $interviewDate,
                    'channel' => $channel,
                    'notes' => $notes,
                ]);

                $imported++;
            }

            break;
        }

        $reader->close();

        $this->info("Imported {$imported} applications for {$user->email}. Skipped {$skipped} rows.");

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

    protected function resolveArea(User $user, string $name): Area
    {
        $name = $name !== '' ? $name : 'General';

        return $user->areas()->firstOrCreate(['name' => $name]);
    }

    protected function parseStatus(mixed $value): ?ApplicationStatus
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return $this->statusMap[$normalized] ?? ApplicationStatus::tryFrom($normalized);
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestampUTC(((float) $value - 25569) * 86400);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }
}
