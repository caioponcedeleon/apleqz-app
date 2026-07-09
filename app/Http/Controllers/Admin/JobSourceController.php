<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JobListingField;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobSourceRequest;
use App\Models\JobSource;
use App\Support\JobExtractionConfigValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobSourceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JobSource::class);

        return Inertia::render('Admin/JobSources/Index', [
            'jobSources' => JobSource::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'url',
                    'company_name',
                    'is_active',
                    'config_version',
                    'last_scraped_at',
                    'last_scrape_status',
                ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', JobSource::class);

        return Inertia::render('Admin/JobSources/Create');
    }

    public function store(JobSourceRequest $request): RedirectResponse
    {
        $this->authorize('create', JobSource::class);

        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['extraction_config'] = JobSource::defaultExtractionConfig();
        $data['config_version'] = 1;

        app(JobExtractionConfigValidator::class)->validate(
            $data['extraction_config'],
            $data['is_active'],
        );

        $source = JobSource::query()->create($data);

        return redirect()
            ->route('job-sources.configure', $source)
            ->with('success', __('app.job_sources.flash.created'));
    }

    public function edit(JobSource $jobSource): Response
    {
        $this->authorize('update', $jobSource);

        return Inertia::render('Admin/JobSources/Edit', [
            'jobSource' => $jobSource->only([
                'id',
                'name',
                'url',
                'company_name',
                'is_active',
                'config_version',
            ]),
        ]);
    }

    public function update(JobSourceRequest $request, JobSource $jobSource): RedirectResponse
    {
        $this->authorize('update', $jobSource);

        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        app(JobExtractionConfigValidator::class)->validate(
            $jobSource->extraction_config ?? JobSource::defaultExtractionConfig(),
            $data['is_active'],
        );

        $jobSource->update($data);

        return redirect()
            ->route('job-sources.index')
            ->with('success', __('app.job_sources.flash.updated'));
    }

    public function destroy(JobSource $jobSource): RedirectResponse
    {
        $this->authorize('delete', $jobSource);

        $jobSource->delete();

        return redirect()
            ->route('job-sources.index')
            ->with('success', __('app.job_sources.flash.deleted'));
    }
}
