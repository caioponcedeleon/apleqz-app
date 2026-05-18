<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationMomentType;
use App\Enums\ApplicationStatus;
use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Services\ApplicationMomentSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function __construct(
        protected ApplicationMomentSyncService $momentSync
    ) {}

    public function index(Request $request): Response
    {
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString();

        if (! in_array($sort, ['position', 'company', 'area', 'applied_at', 'status'], true)) {
            $sort = 'applied_at';
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $query = $request->user()
            ->applications()
            ->with('area');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->string('area_id'));
        }

        if ($request->filled('search')) {
            $term = '%'.mb_strtolower($request->string('search')).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(position) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(company) LIKE ?', [$term]);
            });
        }

        $this->applySorting($query, $sort, $direction);

        return Inertia::render('Applications/Index', [
            'applications' => $query->paginate(15)->withQueryString(),
            'filters' => [
                'status' => $request->input('status'),
                'area_id' => $request->input('area_id'),
                'search' => $request->input('search'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'areas' => $request->user()->areas()->orderBy('name')->get(['id', 'name']),
            'statuses' => ApplicationStatus::values(),
        ]);
    }

    protected function applySorting($query, string $sort, string $direction): void
    {
        match ($sort) {
            'position' => $query->orderBy('position', $direction),
            'company' => $query->orderBy('company', $direction),
            'status' => $query->orderBy('status', $direction),
            'area' => $query
                ->leftJoin('areas', 'applications.area_id', '=', 'areas.id')
                ->orderBy('areas.name', $direction)
                ->select('applications.*'),
            default => $query
                ->orderByRaw('CASE WHEN applied_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('applied_at', $direction),
        };
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Applications/Form', $this->formProps($request, null));
    }

    public function store(ApplicationRequest $request): RedirectResponse
    {
        $application = $request->user()->applications()->create($request->applicationAttributes());
        $this->momentSync->sync($application, $request->momentsPayload());

        return redirect()->route('applications.index')
            ->with('success', __('app.flash.application_created'));
    }

    public function edit(Application $application): Response
    {
        $this->authorize('update', $application);

        $application->load(['area', 'moments', 'files']);

        return Inertia::render('Applications/Form', $this->formProps(request(), $application));
    }

    public function update(ApplicationRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $application->update($request->applicationAttributes());
        $this->momentSync->sync($application, $request->momentsPayload());

        return redirect()->route('applications.index')
            ->with('success', __('app.flash.application_updated'));
    }

    public function destroy(Application $application): RedirectResponse
    {
        $this->authorize('delete', $application);

        $application->delete();

        return redirect()->route('applications.index')
            ->with('success', __('app.flash.application_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formProps(Request $request, ?Application $application): array
    {
        $user = $request->user();

        return [
            'application' => $application,
            'areas' => $user->areas()->orderBy('name')->get(['id', 'name']),
            'statuses' => ApplicationStatus::values(),
            'momentTypes' => ApplicationMomentType::values(),
            'canUploadApplicationFiles' => $user->application_files_enabled,
        ];
    }
}
