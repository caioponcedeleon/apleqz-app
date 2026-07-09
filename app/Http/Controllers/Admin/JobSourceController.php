<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JobScrapeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobSourceRequest;
use App\Models\JobSource;
use App\Services\JobScrapeService;
use App\Services\JobSourceConfigRevisionService;
use App\Support\JobExtractionConfigValidator;
use Illuminate\Http\JsonResponse;
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
                ->orderBy('company_name')
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
        $data['is_active'] = false;
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

        $this->validateExtractionForActive(
            $jobSource->extraction_config ?? JobSource::defaultExtractionConfig(),
            $data['is_active'],
        );

        $jobSource->update($data);

        return redirect()
            ->route('job-sources.index')
            ->with('success', __('app.job_sources.flash.updated'));
    }

    public function toggleActive(Request $request, JobSource $jobSource): RedirectResponse
    {
        $this->authorize('update', $jobSource);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = (bool) $validated['is_active'];

        $this->validateExtractionForActive(
            $jobSource->extraction_config ?? JobSource::defaultExtractionConfig(),
            $isActive,
        );

        $jobSource->update(['is_active' => $isActive]);

        return back();
    }

    public function destroy(JobSource $jobSource): RedirectResponse
    {
        $this->authorize('delete', $jobSource);

        $jobSource->delete();

        return redirect()
            ->route('job-sources.index')
            ->with('success', __('app.job_sources.flash.deleted'));
    }

    public function scrape(Request $request, JobSource $jobSource, JobScrapeService $scraper): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $jobSource);

        $run = $scraper->scrape($jobSource);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $run->status->value,
                'listings_found' => $run->listings_found,
                'listings_new' => $run->listings_new,
                'error_message' => $run->error_message,
            ], $run->status === JobScrapeStatus::Failed ? 422 : 200);
        }

        if ($run->status === JobScrapeStatus::Success) {
            return redirect()
                ->route('job-sources.index')
                ->with('success', __('app.job_sources.flash.scrape_success', [
                    'found' => $run->listings_found,
                    'new' => $run->listings_new,
                ]));
        }

        if ($run->status === JobScrapeStatus::Partial) {
            return redirect()
                ->route('job-sources.index')
                ->with('warning', __('app.job_sources.flash.scrape_zero_listings', [
                    'found' => $run->listings_found,
                ]));
        }

        return redirect()
            ->route('job-sources.index')
            ->with('error', __('app.job_sources.flash.scrape_failed', [
                'error' => $run->error_message ?? __('app.job_sources.flash.scrape_failed_unknown'),
            ]));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function validateExtractionForActive(array $config, bool $isActive): void
    {
        try {
            app(JobExtractionConfigValidator::class)->validate($config, $isActive);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            if ($isActive && isset($exception->errors()['extraction_config'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'is_active' => [__('app.job_sources.errors.cannot_activate_before_config')],
                ]);
            }

            throw $exception;
        }
    }
}
