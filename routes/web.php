<?php

use App\Http\Controllers\Admin\ClassTypeController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\SessionOpsController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Instructor\InstructorSessionController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WaitlistController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Welcome')->name('home');

// System: signature-verified, CSRF-exempt (see bootstrap/app.php).
Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

// Public: guests browse the catalog; mutation CTAs render as "login".
Route::get('catalog', [CatalogController::class, 'index'])->name('catalog');
Route::get('sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');

Route::middleware(['auth', 'verified'])->group(function () {
    // Single post-login entry point: redirects to the role's home.
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Booking flow (students; policies gate roles and ownership).
    Route::post('sessions/{session}/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('bookings/{booking}/confirmation', [BookingController::class, 'confirmation'])->name('bookings.confirmation');
    Route::delete('bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');
    Route::post('bookings/{booking}/pay', [BookingController::class, 'pay'])->name('bookings.pay');
    Route::get('my/bookings', [BookingController::class, 'index'])->name('bookings.index');

    Route::post('sessions/{session}/waitlist', [WaitlistController::class, 'store'])->name('waitlist.store');
    Route::delete('waitlist/{entry}', [WaitlistController::class, 'destroy'])->name('waitlist.destroy');

    Route::middleware('role:instructor')->prefix('instructor')->name('instructor.')->group(function () {
        Route::get('sessions', [InstructorSessionController::class, 'index'])->name('sessions');
        Route::get('sessions/{session}', [InstructorSessionController::class, 'show'])->name('sessions.show');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');

        Route::post('sessions/{session}/cancel', [SessionOpsController::class, 'cancel'])->name('sessions.cancel');
        Route::patch('sessions/{session}/capacity', [SessionOpsController::class, 'updateCapacity'])->name('sessions.capacity');

        Route::resource('class-types', ClassTypeController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);
        Route::resource('schedules', ScheduleController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);
        Route::post('schedules/{schedule}/regenerate', [ScheduleController::class, 'regenerate'])
            ->name('schedules.regenerate');
    });
});

require __DIR__.'/settings.php';
