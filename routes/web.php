<?php

use App\Http\Controllers\Admin\ClassTypeController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Welcome')->name('home');

// Public: guests browse the catalog; mutation CTAs render as "login".
Route::get('catalog', [CatalogController::class, 'index'])->name('catalog');
Route::get('sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');

Route::middleware(['auth', 'verified'])->group(function () {
    // Single post-login entry point: redirects to the role's home.
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:instructor')->prefix('instructor')->name('instructor.')->group(function () {
        Route::get('sessions', fn () => Inertia::render('Instructor/Sessions/Index'))->name('sessions');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');

        Route::resource('class-types', ClassTypeController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);
        Route::resource('schedules', ScheduleController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);
        Route::post('schedules/{schedule}/regenerate', [ScheduleController::class, 'regenerate'])
            ->name('schedules.regenerate');
    });
});

require __DIR__.'/settings.php';
