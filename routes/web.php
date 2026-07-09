<?php

use App\Http\Controllers\Admin\JobSourceConfiguratorController;
use App\Http\Controllers\Admin\JobSourceController;
use App\Http\Controllers\ApplicationMomentController;
use App\Http\Controllers\ApplicationReminderController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationExportController;
use App\Http\Controllers\ApplicationFileController;
use App\Http\Controllers\ApplicationImportController;
use App\Http\Controllers\ApplicationWaveController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobAlertMatchesController;
use App\Http\Controllers\JobAlertSettingsController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserFileController;
use App\Http\Controllers\WaveSelectionController;
use App\Support\UserHome;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect(UserHome::route(auth()->user()))
        : Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
        ]);
});

Route::post('/locale', LocaleController::class)->name('locale.update');
Route::post('/wave', WaveSelectionController::class)->middleware(['auth', 'verified'])->name('wave.select');

Route::get('/cookies', fn () => Inertia::render('Legal/Cookies'))->name('cookies');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware(['user.has.waves'])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::resource('applications', ApplicationController::class)->except(['show', 'store']);
        Route::patch('/applications/{application}/favourite', [ApplicationController::class, 'toggleFavourite'])
            ->name('applications.favourite');
        Route::post('/applications/import', ApplicationImportController::class)->name('applications.import');
        Route::post('/applications/export', ApplicationExportController::class)->name('applications.export');
        Route::post('/applications/{application}/files', [ApplicationFileController::class, 'store'])
            ->name('applications.files.store');
        Route::patch('/applications/{application}/files/{file}', [ApplicationFileController::class, 'update'])
            ->name('applications.files.update')
            ->scopeBindings();
        Route::delete('/applications/{application}/files/{file}', [ApplicationFileController::class, 'destroy'])
            ->name('applications.files.destroy')
            ->scopeBindings();
        Route::get('/applications/{application}/files/{file}/download', [ApplicationFileController::class, 'download'])
            ->name('applications.files.download')
            ->scopeBindings();
        Route::get('/applications/{application}/files/{file}/preview', [ApplicationFileController::class, 'preview'])
            ->name('applications.files.preview')
            ->scopeBindings();

        Route::post('/applications/{application}/reminders', [ApplicationReminderController::class, 'store'])
            ->name('applications.reminders.store');
        Route::patch('/applications/{application}/reminders/{reminder}', [ApplicationReminderController::class, 'update'])
            ->name('applications.reminders.update');
        Route::patch('/applications/{application}/reminders/{reminder}/toggle-active', [ApplicationReminderController::class, 'toggleActive'])
            ->name('applications.reminders.toggle-active');
        Route::delete('/applications/{application}/reminders/{reminder}', [ApplicationReminderController::class, 'destroy'])
            ->name('applications.reminders.destroy');

        Route::post('/applications/{application}/moments', [ApplicationMomentController::class, 'store'])
            ->name('applications.moments.store');
        Route::patch('/applications/{application}/moments/{moment}', [ApplicationMomentController::class, 'update'])
            ->name('applications.moments.update');
        Route::delete('/applications/{application}/moments/{moment}', [ApplicationMomentController::class, 'destroy'])
            ->name('applications.moments.destroy');
    });

    Route::post('applications', [ApplicationController::class, 'store'])
        ->middleware(['user.has.areas', 'user.has.waves'])
        ->name('applications.store');

    Route::get('/files', [UserFileController::class, 'index'])->name('files.index');
    Route::post('/files', [UserFileController::class, 'store'])->name('files.store');
    Route::patch('/files/{file}', [UserFileController::class, 'update'])->name('files.update');
    Route::delete('/files/{file}', [UserFileController::class, 'destroy'])->name('files.destroy');
    Route::get('/files/{file}/download', [UserFileController::class, 'download'])->name('files.download');
    Route::get('/files/{file}/preview', [UserFileController::class, 'preview'])->name('files.preview');

    Route::get('/areas', [AreaController::class, 'index'])->name('areas.index');
    Route::post('/areas', [AreaController::class, 'store'])->name('areas.store');
    Route::put('/areas/{area}', [AreaController::class, 'update'])->name('areas.update');
    Route::delete('/areas/{area}', [AreaController::class, 'destroy'])->name('areas.destroy');

    Route::get('/waves', [ApplicationWaveController::class, 'index'])->name('waves.index');
    Route::post('/waves', [ApplicationWaveController::class, 'store'])->name('waves.store');
    Route::put('/waves/{application_wave}', [ApplicationWaveController::class, 'update'])->name('waves.update');
    Route::post('/waves/{application_wave}/default', [ApplicationWaveController::class, 'makeDefault'])->name('waves.default');
    Route::delete('/waves/{application_wave}', [ApplicationWaveController::class, 'destroy'])->name('waves.destroy');

    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::redirect('/job-alerts', '/job-alerts/settings')->name('job-alerts.index');
    Route::get('/job-alerts/settings', [JobAlertSettingsController::class, 'edit'])->name('job-alerts.settings');
    Route::patch('/job-alerts/settings', [JobAlertSettingsController::class, 'update'])->name('job-alerts.settings.update');
    Route::get('/job-alerts/matches', [JobAlertMatchesController::class, 'index'])->name('job-alerts.matches');
    Route::patch('/job-alerts/matches/{jobMatch}/dismiss', [JobAlertMatchesController::class, 'dismiss'])->name('job-alerts.matches.dismiss');
    Route::get('/job-alerts/matches/{jobMatch}/apply', [JobAlertMatchesController::class, 'apply'])->name('job-alerts.matches.apply');

    Route::middleware(['admin'])->prefix('job-sources')->name('job-sources.')->group(function () {
        Route::get('/', [JobSourceController::class, 'index'])->name('index');
        Route::get('/create', [JobSourceController::class, 'create'])->name('create');
        Route::post('/', [JobSourceController::class, 'store'])->name('store');
        Route::post('/preview', [JobSourceConfiguratorController::class, 'preview'])->name('preview');
        Route::post('/test-extraction', [JobSourceConfiguratorController::class, 'testExtraction'])->name('test-extraction');
        Route::get('/{jobSource}/configure', [JobSourceConfiguratorController::class, 'edit'])->name('configure');
        Route::patch('/{jobSource}/extraction-config', [JobSourceConfiguratorController::class, 'update'])->name('extraction-config.update');
        Route::get('/{jobSource}/edit', [JobSourceController::class, 'edit'])->name('edit');
        Route::put('/{jobSource}', [JobSourceController::class, 'update'])->name('update');
        Route::post('/{jobSource}/scrape', [JobSourceController::class, 'scrape'])->name('scrape');
        Route::delete('/{jobSource}', [JobSourceController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/auth.php';
