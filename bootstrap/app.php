<?php

use App\Http\Middleware\EnsureUserHasAreas;
use App\Http\Middleware\EnsureUserHasJobAlerts;
use App\Http\Middleware\EnsureUserHasWaves;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('applications:send-reminders')->everyThirtyMinutes();
        $schedule->command('jobs:scrape-sources')->dailyAt('09:00')->timezone('UTC');
        $schedule->command('jobs:scrape-sources')->dailyAt('19:00')->timezone('UTC');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'user.has.areas' => EnsureUserHasAreas::class,
            'user.has.waves' => EnsureUserHasWaves::class,
            'admin' => EnsureUserIsAdmin::class,
            'user.has.job_alerts' => EnsureUserHasJobAlerts::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
