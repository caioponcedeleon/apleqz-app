<?php

namespace App\Http\Controllers;

use App\Models\ApplicationWave;
use App\Services\SelectedWaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WaveSelectionController extends Controller
{
    public function __invoke(Request $request, SelectedWaveService $selectedWave): RedirectResponse
    {
        $wave = ApplicationWave::query()->findOrFail($request->string('wave_id')->toString());

        $selectedWave->select($request, $request->user(), $wave);

        return back();
    }
}
