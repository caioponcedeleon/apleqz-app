<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Services\ApplicationStatisticsService;
use App\Services\SelectedWaveService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected ApplicationStatisticsService $statistics
    ) {}

    public function __invoke(Request $request, SelectedWaveService $selectedWave): Response
    {
        $user = $request->user();
        $wave = $selectedWave->forRequest($request, $user);
        $stats = $this->statistics->forUser($user, $wave?->id);

        return Inertia::render('Dashboard', [
            'statistics' => $stats,
            'statuses' => collect(ApplicationStatus::cases())->map(fn (ApplicationStatus $s) => [
                'value' => $s->value,
                'color' => $s->badgeColor(),
            ]),
        ]);
    }
}
