<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaitlistEntry;

it('serves kpis and streams occupancy as a deferred prop', function () {
    $session = ClassSession::factory()->create([
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
        'capacity' => 10,
    ]);
    Booking::factory()->confirmed()->create(['class_session_id' => $session->id]);
    $session->increment('booked_count');
    WaitlistEntry::factory()->create();
    Payment::factory()->succeeded()->create(['amount_cents' => 2500]);

    $admin = User::factory()->admin()->create();

    // First paint: KPIs present, deferred occupancy absent.
    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('kpis.sessions_next_7d', fn ($value) => $value >= 1)
            ->where('kpis.waiting_now', 1)
            ->hasAll(['kpis.confirmed_next_7d', 'kpis.collected_cents', 'scheduler.healthy'])
            ->missing('occupancy')); // deferred: streams in after first paint
});

it('shows the admin week view with occupancy and waiting counts', function () {
    $this->travelTo('2026-06-15 08:00:00');
    $session = ClassSession::factory()->create([
        'local_date' => '2026-06-17',
        'starts_at' => '2026-06-17 14:00:00',
        'ends_at' => '2026-06-17 15:00:00',
    ]);
    WaitlistEntry::factory()->count(2)->create(['class_session_id' => $session->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/sessions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Sessions/Index')
            ->has('sessions', 1, fn ($row) => $row
                ->where('id', $session->id)
                ->where('waiting', 2)
                ->hasAll(['name', 'instructor', 'starts_at', 'status', 'booked', 'capacity'])
            )
            ->hasAll(['week.start', 'week.prev', 'week.next']));
});

it('gates the new admin pages to admins only', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get('/admin')->assertForbidden();
    $this->actingAs(User::factory()->{$state}()->create())->get('/admin/sessions')->assertForbidden();
})->with(['student', 'instructor']);
