<?php

namespace App\Services;

use App\Enums\ApplicationMomentType;
use App\Models\Application;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ApplicationMomentSyncService
{
    /**
     * @param  list<array<string, mixed>>  $moments
     */
    public function sync(Application $application, array $moments): void
    {
        $keptIds = [];

        foreach ($moments as $index => $moment) {
            if (! is_array($moment)) {
                continue;
            }

            $type = ApplicationMomentType::tryFrom((string) ($moment['type'] ?? ''));

            if (! $type || $type === ApplicationMomentType::StatusChange || empty($moment['occurred_at'])) {
                continue;
            }

            $payload = [
                'type' => $type,
                'occurred_at' => $moment['occurred_at'],
                'notes' => Arr::get($moment, 'notes') ?: null,
                'sort_order' => $index,
            ];

            if (! empty($moment['id'])) {
                $record = $application->moments()->whereKey($moment['id'])->first();

                if (! $record || $record->is_system) {
                    if ($record?->is_system) {
                        continue;
                    }

                    throw ValidationException::withMessages([
                        "moments.{$index}.id" => __('validation.exists', ['attribute' => 'moment']),
                    ]);
                }

                $record->update($payload);
                $keptIds[] = $record->id;

                continue;
            }

            $record = $application->moments()->create([
                ...$payload,
                'is_system' => false,
            ]);
            $keptIds[] = $record->id;
        }

        $application->moments()
            ->where('is_system', false)
            ->whereNotIn('id', $keptIds)
            ->delete();
    }
}
