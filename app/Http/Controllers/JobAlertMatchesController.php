<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class JobAlertMatchesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('JobAlerts/Matches', [
            'matches' => [],
        ]);
    }
}
