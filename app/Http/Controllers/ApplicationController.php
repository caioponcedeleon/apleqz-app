<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->user()
            ->applications()
            ->with('area')
            ->latest('applied_at');

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

        return Inertia::render('Applications/Index', [
            'applications' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['status', 'area_id', 'search']),
            'areas' => $request->user()->areas()->orderBy('name')->get(['id', 'name']),
            'statuses' => ApplicationStatus::values(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Applications/Form', [
            'application' => null,
            'areas' => $request->user()->areas()->orderBy('name')->get(['id', 'name']),
            'statuses' => ApplicationStatus::values(),
        ]);
    }

    public function store(ApplicationRequest $request): RedirectResponse
    {
        $request->user()->applications()->create($request->validated());

        return redirect()->route('applications.index')
            ->with('success', __('app.flash.application_created'));
    }

    public function edit(Application $application): Response
    {
        $this->authorize('update', $application);

        $application->load('area');

        return Inertia::render('Applications/Form', [
            'application' => $application,
            'areas' => auth()->user()->areas()->orderBy('name')->get(['id', 'name']),
            'statuses' => ApplicationStatus::values(),
        ]);
    }

    public function update(ApplicationRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $application->update($request->validated());

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
}
