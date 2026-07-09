<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationWaveRequest;
use App\Models\ApplicationWave;
use App\Services\SelectedWaveService;
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

    public function store(ApplicationWaveRequest $request, SelectedWaveService $selectedWave): RedirectResponse
    {
        $this->authorize('create', ApplicationWave::class);

        $isFirstWave = $request->user()->applicationWaves()->doesntExist();

        $wave = $request->user()->applicationWaves()->create([
            ...$request->validated(),
            'is_default' => $isFirstWave,
        ]);
        $selectedWave->select($request, $request->user(), $wave);

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

        $wasDefault = $application_wave->is_default;
        $user = $application_wave->user;

        $application_wave->delete();

        if ($wasDefault) {
            $user->applicationWaves()->latest()->first()?->update(['is_default' => true]);
        }

        return back()->with('success', __('app.flash.wave_deleted'));
    }

    public function makeDefault(Request $request, ApplicationWave $application_wave): RedirectResponse
    {
        $this->authorize('update', $application_wave);

        $user = $request->user();

        $user->applicationWaves()->update(['is_default' => false]);
        $application_wave->update(['is_default' => true]);

        app(SelectedWaveService::class)->select($request, $user, $application_wave);

        return back()->with('success', __('app.flash.wave_default_set'));
    }
}
