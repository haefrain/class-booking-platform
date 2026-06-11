<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Welcome')->name('home');

// Public: guests browse the catalog; mutation CTAs render as "login".
Route::get('catalog', fn () => Inertia::render('Catalog/Index'))->name('catalog');

Route::middleware(['auth', 'verified'])->group(function () {
    // Single post-login entry point: redirects to the role's home.
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:instructor')->prefix('instructor')->name('instructor.')->group(function () {
        Route::get('sessions', fn () => Inertia::render('Instructor/Sessions/Index'))->name('sessions');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');
    });
});

require __DIR__.'/settings.php';
