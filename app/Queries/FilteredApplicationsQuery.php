<?php

namespace App\Queries;

use App\Enums\ApplicationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class FilteredApplicationsQuery
{
    public function __construct(
        protected User $user,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function build(array $filters): Builder|Relation
    {
        $sort = $filters['sort'] ?? 'status';
        $direction = $filters['direction'] ?? 'asc';

        if (! in_array($sort, ['position', 'company', 'area', 'wave', 'applied_at', 'status'], true)) {
            $sort = 'status';
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        $query = $this->user
            ->applications()
            ->with(['area', 'wave', 'moments']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['area_id'])) {
            $query->where('area_id', $filters['area_id']);
        }

        if (! empty($filters['wave_id'])) {
            $query->where('application_wave_id', $filters['wave_id']);
        }

        if (! empty($filters['favourites'])) {
            $query->where('is_favourite', true);
        }

        if (! empty($filters['search'])) {
            $term = '%'.mb_strtolower((string) $filters['search']).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(position) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(company) LIKE ?', [$term]);
            });
        }

        $this->applySorting($query, $sort, $direction);

        return $query;
    }

    protected function applySorting(Builder|Relation $query, string $sort, string $direction): void
    {
        match ($sort) {
            'position' => $query->orderBy('position', $direction),
            'company' => $query->orderBy('company', $direction),
            'status' => $this->applyStatusSorting($query, $direction),
            'area' => $query
                ->leftJoin('areas', 'applications.area_id', '=', 'areas.id')
                ->orderBy('areas.name', $direction)
                ->select('applications.*'),
            'wave' => $query
                ->leftJoin('application_waves', 'applications.application_wave_id', '=', 'application_waves.id')
                ->orderBy('application_waves.name', $direction)
                ->select('applications.*'),
            default => $query
                ->orderByRaw('CASE WHEN applied_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('applied_at', $direction),
        };
    }

    protected function applyStatusSorting(Builder|Relation $query, string $direction): void
    {
        $query
            ->orderByDesc('is_favourite')
            ->orderByRaw(ApplicationStatus::listSortOrderSql($direction))
            ->orderByRaw('CASE WHEN applied_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('applied_at')
            ->orderBy('position');
    }
}
