<?php

namespace App\Services;

use App\DataTransferObjects\ApplicationImportResult;
use App\Enums\ApplicationStatus;
use App\Exceptions\ApplicationImportException;
use App\Enums\ApplicationMomentType;
use App\Models\Application;
use App\Models\Area;
use App\Models\User;
use Carbon\Carbon;
use OpenSpout\Reader\XLSX\Reader;

class ApplicationImportService
{
    /** @var array<string, ApplicationStatus> */
    protected array $statusMap = [
        'a_candidatar' => ApplicationStatus::WaitingToApply,
        'a candidatar' => ApplicationStatus::WaitingToApply,
        'por candidatar' => ApplicationStatus::WaitingToApply,
        'esperando' => ApplicationStatus::Waiting,
        'rejeitado' => ApplicationStatus::Rejected,
        'oferta' => ApplicationStatus::Offer,
        'recusado' => ApplicationStatus::DeclinedByMe,
        'retirada' => ApplicationStatus::Withdrawn,
        'cancelada' => ApplicationStatus::Cancelled,
    ];

    public function importFromPath(User $user, string $path): ApplicationImportResult
    {
        if (! is_file($path)) {
            throw new ApplicationImportException(__('app.flash.import_failed'));
        }

        $reader = new Reader;
        $reader->open($path);

        $imported = 0;
        $skipped = 0;
        $sheetFound = false;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== 'Vagas') {
                    continue;
                }

                $sheetFound = true;
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

                    $status = $this->parseStatus($values[5] ?? null);

                    if (! $status) {
                        $skipped++;

                        continue;
                    }

                    $appliedAt = $this->parseDate($values[4] ?? null);

                    if ($status->requiresAppliedDate() && ! $appliedAt) {
                        $skipped++;

                        continue;
                    }

                    $rejectedAt = $this->parseDate($values[6] ?? null);
                    $interviewDate = $this->parseDate($values[8] ?? null);
                    $channel = $this->stringOrNull($values[9] ?? null);
                    $notes = $this->stringOrNull($values[10] ?? null);

                    $application = Application::query()->create([
                        'user_id' => $user->id,
                        'area_id' => $area->id,
                        'position' => trim((string) $values[0]),
                        'company' => trim((string) ($values[2] ?? '')),
                        'location' => $this->stringOrNull($values[3] ?? null),
                        'applied_at' => $appliedAt,
                        'status' => $status,
                        'channel' => $channel,
                        'notes' => $notes,
                    ]);

                    $sort = 0;

                    if ($interviewDate) {
                        $application->moments()->create([
                            'type' => ApplicationMomentType::Interview,
                            'occurred_at' => $interviewDate,
                            'sort_order' => $sort++,
                        ]);
                    } elseif (isset($values[8]) && is_string($values[8]) && trim($values[8]) !== '') {
                        $application->moments()->create([
                            'type' => ApplicationMomentType::Other,
                            'occurred_at' => $appliedAt ?? now()->toDateString(),
                            'notes' => 'Interview note: '.trim($values[8]),
                            'sort_order' => $sort++,
                        ]);
                    }

                    if ($rejectedAt) {
                        $application->moments()->create([
                            'type' => ApplicationMomentType::Rejection,
                            'occurred_at' => $rejectedAt,
                            'sort_order' => $sort++,
                        ]);
                    }

                    $imported++;
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if (! $sheetFound) {
            throw new ApplicationImportException(__('app.flash.import_no_sheet'));
        }

        return new ApplicationImportResult($imported, $skipped);
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
