<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationFileController;
use App\Http\Controllers\ApplicationImportController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserFileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
        ]);
});

Route::post('/locale', LocaleController::class)->name('locale.update');

Route::get('/cookies', fn () => Inertia::render('Legal/Cookies'))->name('cookies');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('applications', ApplicationController::class)->except(['show']);
    Route::post('/applications/import', ApplicationImportController::class)->name('applications.import');
    Route::post('/applications/{application}/files', [ApplicationFileController::class, 'store'])
        ->name('applications.files.store');
    Route::delete('/applications/{application}/files/{file}', [ApplicationFileController::class, 'destroy'])
        ->name('applications.files.destroy')
        ->scopeBindings();
    Route::get('/applications/{application}/files/{file}/download', [ApplicationFileController::class, 'download'])
        ->name('applications.files.download')
        ->scopeBindings();
    Route::get('/applications/{application}/files/{file}/preview', [ApplicationFileController::class, 'preview'])
        ->name('applications.files.preview')
        ->scopeBindings();

    Route::get('/files', [UserFileController::class, 'index'])->name('files.index');
    Route::post('/files', [UserFileController::class, 'store'])->name('files.store');
    Route::delete('/files/{file}', [UserFileController::class, 'destroy'])->name('files.destroy');
    Route::get('/files/{file}/download', [UserFileController::class, 'download'])->name('files.download');
    Route::get('/files/{file}/preview', [UserFileController::class, 'preview'])->name('files.preview');

    Route::get('/areas', [AreaController::class, 'index'])->name('areas.index');
    Route::post('/areas', [AreaController::class, 'store'])->name('areas.store');
    Route::put('/areas/{area}', [AreaController::class, 'update'])->name('areas.update');
    Route::delete('/areas/{area}', [AreaController::class, 'destroy'])->name('areas.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
