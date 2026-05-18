<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Services\ApplicationStatisticsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected ApplicationStatisticsService $statistics
    ) {}

    public function __invoke(): Response
    {
        $user = auth()->user();
        $stats = $this->statistics->forUser($user);

        return Inertia::render('Dashboard', [
            'statistics' => $stats,
            'statuses' => collect(ApplicationStatus::cases())->map(fn (ApplicationStatus $s) => [
                'value' => $s->value,
                'color' => $s->badgeColor(),
            ]),
        ]);
    }
}
