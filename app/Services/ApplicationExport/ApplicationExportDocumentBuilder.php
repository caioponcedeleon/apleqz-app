<?php

namespace App\Services\ApplicationExport;

use App\DataTransferObjects\ApplicationExportDocument;
use App\DataTransferObjects\ApplicationExportOptions;
use App\Models\Application;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ApplicationExportDocumentBuilder
{
    public function build(
        User $user,
        Collection $applications,
        ApplicationExportOptions $options,
    ): ApplicationExportDocument {
        $headers = $this->headers($options);
        $rows = $applications
            ->map(fn (Application $application) => $this->row($application, $options, $headers))
            ->all();

        $meta = [];

        if ($options->agenturFurArbeit) {
            $meta['applicant'] = $user->name;
            $meta['exported_at'] = now()->timezone(config('app.timezone'))->format('d.m.Y');
            $meta['footnote'] = __('export.footnote', [], 'de');
        }

        return new ApplicationExportDocument(
            title: $this->title($options),
            headers: $headers,
            rows: $rows,
            meta: $meta,
        );
    }

    /**
     * @return list<string>
     */
    protected function headers(ApplicationExportOptions $options): array
    {
        $labels = $options->agenturFurArbeit
            ? $this->agenturLabels()
            : $this->defaultLabels();

        return array_values(array_map(
            fn (string $field) => $labels[$field],
            $options->fields,
        ));
    }

    /**
     * @param  list<string>  $headers
     * @return list<string>
     */
    protected function row(Application $application, ApplicationExportOptions $options, array $headers): array
    {
        $values = [];

        foreach ($options->fields as $field) {
            $values[] = match ($field) {
                'position' => $application->position,
                'company' => $application->company,
                'applied_at' => $this->formatDate($application->applied_at?->format('Y-m-d'), $options),
                'status' => $this->formatStatus($application->status->value, $options),
                'events' => $this->formatEvents($application, $options),
                default => '',
            };
        }

        return $values;
    }

    protected function title(ApplicationExportOptions $options): string
    {
        if ($options->agenturFurArbeit) {
            return __('export.title', [], 'de');
        }

        return __('app.export.document_title');
    }

    protected function formatDate(?string $isoDate, ApplicationExportOptions $options): string
    {
        if (! $isoDate) {
            return $options->agenturFurArbeit ? '—' : '—';
        }

        $date = Carbon::parse($isoDate);

        return $options->agenturFurArbeit
            ? $date->format('d.m.Y')
            : $date->translatedFormat(config('app.export_date_format', 'd M Y'));
    }

    protected function formatStatus(string $status, ApplicationExportOptions $options): string
    {
        if ($options->agenturFurArbeit) {
            return __('export.status.'.$status, [], 'de');
        }

        return __('app.status.'.$status);
    }

    protected function formatEvents(Application $application, ApplicationExportOptions $options): string
    {
        if ($application->moments->isEmpty()) {
            return '';
        }

        return $application->moments
            ->map(function ($moment) use ($options) {
                $date = $this->formatDate($moment->occurred_at?->format('Y-m-d'), $options);
                $type = $options->agenturFurArbeit
                    ? __('export.moment_types.'.$moment->type->value, [], 'de')
                    : __('app.moment_types.'.$moment->type->value);

                $line = "{$date} – {$type}";

                if (filled($moment->notes)) {
                    $line .= ': '.$moment->notes;
                }

                return $line;
            })
            ->implode($options->agenturFurArbeit ? '; ' : "\n");
    }

    /**
     * @return array<string, string>
     */
    protected function defaultLabels(): array
    {
        return [
            'position' => __('app.applications.position'),
            'company' => __('app.applications.company'),
            'applied_at' => __('app.applications.applied_at'),
            'status' => __('app.applications.status'),
            'events' => __('app.export.field_events'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function agenturLabels(): array
    {
        return [
            'position' => __('export.position', [], 'de'),
            'company' => __('export.company', [], 'de'),
            'applied_at' => __('export.applied_at', [], 'de'),
            'status' => __('export.status_header', [], 'de'),
            'events' => __('export.events', [], 'de'),
        ];
    }
}
