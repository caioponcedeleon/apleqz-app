<?php

namespace App\Services;

use App\Models\ApplicationWave;
use App\Models\User;
use Illuminate\Http\Request;

class SelectedWaveService
{
    public function forRequest(Request $request, User $user): ?ApplicationWave
    {
        $waveId = $request->session()->get('wave_id') ?? $user->current_wave_id;

        if ($waveId) {
            $wave = $user->applicationWaves()->find($waveId);

            if ($wave) {
                return $wave;
            }
        }

        return $user->applicationWaves()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();
    }

    public function select(Request $request, User $user, ApplicationWave $wave): void
    {
        abort_unless($wave->user_id === $user->id, 403);

        $request->session()->put('wave_id', $wave->id);
        $user->update(['current_wave_id' => $wave->id]);
    }
}
