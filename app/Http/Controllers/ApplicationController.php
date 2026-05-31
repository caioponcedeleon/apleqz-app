<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationMomentType;
use App\Enums\ApplicationStatus;
use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Queries\FilteredApplicationsQuery;
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
        $filters = [
            'status' => $request->input('status'),
            'area_id' => $request->input('area_id'),
            'search' => $request->input('search'),
            'sort' => $request->input('sort', 'applied_at'),
            'direction' => $request->input('direction', 'desc'),
        ];

        $query = (new FilteredApplicationsQuery($request->user()))
            ->build($filters)
            ->with('area');

        return Inertia::render('Applications/Index', [
            'applications' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'areas' => $request->user()->areas()->orderBy('name')->get(['id', 'name']),
            'statuses' => ApplicationStatus::values(),
        ]);
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
