<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationWaveRequest;
use App\Models\ApplicationWave;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationWaveController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ApplicationWave::class);

        return Inertia::render('Waves/Index', [
            'waves' => $request->user()
                ->applicationWaves()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'starts_at', 'ends_at', 'is_default']),
        ]);
    }

    public function store(ApplicationWaveRequest $request): RedirectResponse
    {
        $this->authorize('create', ApplicationWave::class);

        $request->user()->applicationWaves()->create($request->validated());

        return back()->with('success', __('app.flash.wave_created'));
    }

    public function update(ApplicationWaveRequest $request, ApplicationWave $application_wave): RedirectResponse
    {
        $this->authorize('update', $application_wave);

        $application_wave->update($request->validated());

        return back()->with('success', __('app.flash.wave_updated'));
    }

    public function destroy(ApplicationWave $application_wave): RedirectResponse
    {
        $this->authorize('delete', $application_wave);

        if ($application_wave->applications()->exists()) {
            return back()->with('error', __('app.flash.wave_in_use'));
        }

        $application_wave->delete();

        return back()->with('success', __('app.flash.wave_deleted'));
    }
}
