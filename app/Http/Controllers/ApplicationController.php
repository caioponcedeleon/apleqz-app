<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationMomentType;
use App\Enums\ApplicationStatus;
use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Queries\FilteredApplicationsQuery;
use App\Services\ApplicationMomentSyncService;
use App\Services\ApplicationStatusHistoryService;
use App\Services\SelectedWaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function __construct(
        protected ApplicationMomentSyncService $momentSync,
        protected ApplicationStatusHistoryService $statusHistory,
    ) {}

    public function index(Request $request, SelectedWaveService $selectedWave): Response
    {
        $wave = $selectedWave->forRequest($request, $request->user());

        $filters = [
            'status' => $request->input('status'),
            'area_id' => $request->input('area_id'),
            'wave_id' => $wave?->id,
            'favourites' => $request->boolean('favourites'),
            'search' => $request->input('search'),
            'sort' => $request->input('sort', 'status'),
            'direction' => $request->input('direction', 'asc'),
        ];

        $query = (new FilteredApplicationsQuery($request->user()))
            ->build($filters)
            ->with(['area', 'wave']);

        return Inertia::render('Applications/Index', [
            'applications' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'areas' => $request->user()->areas()->orderBy('name')->get(['id', 'name']),
            'statuses' => ApplicationStatus::values(),
            'canCreateApplication' => $request->user()->areas()->exists()
                && $request->user()->applicationWaves()->exists(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Applications/Form', $this->formProps($request, null));
    }

    public function store(ApplicationRequest $request): RedirectResponse
    {
        $application = $request->user()->applications()->create($request->applicationAttributes());
        $this->statusHistory->recordInitial($application);
        $this->momentSync->sync($application, $request->momentsPayload());

        if ($request->boolean('create_another')) {
            return redirect()->route('applications.create')
                ->with('success', __('app.flash.application_created'));
        }

        return redirect()->route('applications.edit', $application)
            ->with('success', __('app.flash.application_created'));
    }

    public function edit(Application $application): Response
    {
        $this->authorize('update', $application);

        $application->load(['area', 'wave', 'moments', 'files']);

        return Inertia::render('Applications/Form', $this->formProps(request(), $application));
    }

    public function update(ApplicationRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $previousStatus = $application->status;

        $application->update($request->applicationAttributes());
        $this->statusHistory->recordIfChanged($application, $previousStatus);
        $this->momentSync->sync($application, $request->momentsPayload());

        return redirect()->route('applications.edit', $application)
            ->with('success', __('app.flash.application_updated'));
    }

    public function destroy(Application $application): RedirectResponse
    {
        $this->authorize('delete', $application);

        $application->delete();

        return redirect()->route('applications.index')
            ->with('success', __('app.flash.application_deleted'));
    }

    public function toggleFavourite(Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $application->update(['is_favourite' => ! $application->is_favourite]);

        return back();
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
            'waves' => $user->applicationWaves()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']),
            'statuses' => ApplicationStatus::values(),
            'momentTypes' => ApplicationMomentType::userEditableValues(),
            'canUploadApplicationFiles' => $user->application_files_enabled,
            'canCreateApplication' => $user->areas()->exists()
                && $user->applicationWaves()->exists(),
        ];
    }
}
