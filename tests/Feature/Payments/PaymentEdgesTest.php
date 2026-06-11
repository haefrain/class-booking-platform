<?php

declare(strict_types=1);

use App\Actions\Bookings\BookSeatAction;
use App\Actions\Bookings\CancelBookingAction;
use App\Actions\Payments\ConfirmPaidBookingAction;
use App\Actions\Payments\CreateCheckoutForBookingAction;
use App\Actions\Payments\ExpirePendingBookingAction;
use App\Actions\Sessions\CancelSessionAction;
use App\Actions\Waitlist\JoinWaitlistAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Jobs\ProcessStripeWebhookJob;
use App\Jobs\RefundPaymentJob;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\StripeEvent;
use App\Models\User;
use App\Notifications\PaymentAutoRefundedNotification;
use App\Notifications\SeatLostAfterPaymentNotification;
use App\Payments\PaymentGateway;
use App\Support\SeatInvariantAuditor;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Fakes\FakePaymentGateway;

afterEach(fn () => SeatInvariantAuditor::assertAll());

beforeEach(function () {
    $this->gateway = new FakePaymentGateway;
    $this->swap(PaymentGateway::class, $this->gateway);
});

function edgeSession(int $capacity = 3, int $priceCents = 1500): ClassSession
{
    $type = ClassType::factory()->create(['default_capacity' => $capacity]);
    $type->price_cents = $priceCents;
    $type->save();

    return ClassSession::factory()
        ->forSchedule(Schedule::factory()->for($type)->create())
        ->create(['capacity' => $capacity]);
}

/** Book a paid hold + create its checkout; returns [booking, checkoutId]. */
function holdWithCheckout(User $student, ClassSession $session): array
{
    app(BookSeatAction::class)->handle($student, $session->id, (string) Str::uuid());
    $booking = Booking::query()->where('user_id', $student->id)->latest('id')->firstOrFail();
    app(CreateCheckoutForBookingAction::class)->handle($booking);
    $checkoutId = Payment::query()->where('booking_id', $booking->id)->latest('id')->firstOrFail()
        ->stripe_checkout_session_id;

    return [$booking, $checkoutId];
}

function completedPayload(string $checkoutId, int $amount = 1500, string $intent = 'pi_edge'): array
{
    return ['id' => $checkoutId, 'amount_total' => $amount, 'currency' => 'usd', 'payment_intent' => $intent];
}

it('refunds instead of resurrecting when payment lands after each cancel kind', function (string $kind) {
    Notification::fake();
    $session = edgeSession(capacity: 1);
    $student = User::factory()->student()->create();
    [$booking, $checkoutId] = holdWithCheckout($student, $session);
    $admin = User::factory()->admin()->create();

    match ($kind) {
        'user' => app(CancelBookingAction::class)->handle($booking, $student),
        'admin' => app(CancelBookingAction::class)->handle($booking, $admin),
        'session' => app(CancelSessionAction::class)->handle($session, $admin, 'flood'),
    };

    // The customer pays inside the race window anyway.
    app(ConfirmPaidBookingAction::class)->handle(completedPayload($checkoutId));

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled) // NEVER resurrected
        ->and($this->gateway->refundCalls)->toHaveCount(1)
        ->and($this->gateway->refundCalls[0]['payment_intent'])->toBe('pi_edge');

    Notification::assertSentTo($student, PaymentAutoRefundedNotification::class);
})->with(['user', 'admin', 'session']);

it('resurrects an expired hold only when every guard passes', function () {
    $session = edgeSession(capacity: 1);
    $student = User::factory()->student()->create();
    [$booking, $checkoutId] = holdWithCheckout($student, $session);

    // Hold expires (sweep-shaped), seat frees…
    $this->travel(31)->minutes();
    app(ExpirePendingBookingAction::class)->handle($booking->id);
    expect($session->refresh()->booked_count)->toBe(0);

    // …then the late payment lands while the seat is STILL free → resurrect.
    app(ConfirmPaidBookingAction::class)->handle(completedPayload($checkoutId));

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($session->refresh()->booked_count)->toBe(1)
        ->and($this->gateway->refundCalls)->toHaveCount(0);
});

it('refunds with a seat-lost mail when the freed seat was taken meanwhile', function (string $blocker) {
    Notification::fake();
    $session = edgeSession(capacity: 1);
    $student = User::factory()->student()->create();
    [$booking, $checkoutId] = holdWithCheckout($student, $session);

    $this->travel(31)->minutes();
    app(ExpirePendingBookingAction::class)->handle($booking->id);

    match ($blocker) {
        'seat_taken' => app(BookSeatAction::class)->handle(
            User::factory()->student()->create(), $session->id, (string) Str::uuid()),
        'session_cancelled' => app(CancelSessionAction::class)->handle(
            $session, User::factory()->admin()->create(), 'closed'),
        'session_started' => $session->forceFill([
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(),
        ])->save(),
    };

    app(ConfirmPaidBookingAction::class)->handle(completedPayload($checkoutId));

    expect($booking->refresh()->status)->toBe(BookingStatus::Expired) // stays dead
        ->and($this->gateway->refundCalls)->toHaveCount(1);

    Notification::assertSentTo($student, SeatLostAfterPaymentNotification::class);
})->with(['seat_taken', 'session_cancelled', 'session_started']);

it('refunds exactly once under double dispatch thanks to the atomic claim', function () {
    $payment = Payment::factory()->succeeded()->create();

    RefundPaymentJob::dispatch($payment->id);
    RefundPaymentJob::dispatch($payment->id);

    expect($this->gateway->refundCalls)->toHaveCount(1)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::RefundPending);
});

it('applies the refund policy per cancellation kind', function (string $kind, int $expectedRefunds) {
    $this->travelTo('2026-06-15 12:00:00');
    $session = edgeSession();
    $student = User::factory()->student()->create();
    [$booking, $checkoutId] = holdWithCheckout($student, $session);
    $this->gateway->completeCheckout($checkoutId, 1500, 'pi_policy');
    app(ConfirmPaidBookingAction::class)->handle(completedPayload($checkoutId, 1500, 'pi_policy'));

    if ($kind === 'late') {
        // Inside the 24h deadline window.
        $this->travelTo($session->starts_at->subHours(2)->toDateTimeString());
    }

    $actor = $kind === 'admin' ? User::factory()->admin()->create() : $student;
    app(CancelBookingAction::class)->handle($booking->refresh(), $actor);

    expect($this->gateway->refundCalls)->toHaveCount($expectedRefunds);
})->with([
    'on_time refunds' => ['on_time', 1],
    'late keeps the money' => ['late', 0],
    'admin refunds' => ['admin', 1],
]);

it('flags partial refunds and leaves the seat decision to the admin', function () {
    $payment = Payment::factory()->succeeded()->create();

    StripeEvent::query()->create([
        'stripe_event_id' => 'evt_partial',
        'type' => 'charge.refunded',
        'payload' => ['payment_intent' => $payment->stripe_payment_intent_id, 'amount_refunded' => 500],
    ]);
    app(ProcessStripeWebhookJob::class, ['stripeEventRowId' => StripeEvent::query()->latest('id')->value('id')])
        ->handle(app(ConfirmPaidBookingAction::class), app(ExpirePendingBookingAction::class));

    expect($payment->refresh()->flag)->toBe('partial_refund')
        ->and($payment->status)->toBe(PaymentStatus::Succeeded) // unchanged
        ->and($payment->amount_refunded_cents)->toBe(500);
});

it('flags external full refunds while the booking is still confirmed', function () {
    $session = edgeSession();
    $student = User::factory()->student()->create();
    [$booking, $checkoutId] = holdWithCheckout($student, $session);
    app(ConfirmPaidBookingAction::class)->handle(completedPayload($checkoutId, 1500, 'pi_ext'));
    $payment = Payment::query()->sole();

    StripeEvent::query()->create([
        'stripe_event_id' => 'evt_external',
        'type' => 'charge.refunded',
        'payload' => ['payment_intent' => 'pi_ext', 'amount_refunded' => 1500],
    ]);
    app(ProcessStripeWebhookJob::class, ['stripeEventRowId' => StripeEvent::query()->latest('id')->value('id')])
        ->handle(app(ConfirmPaidBookingAction::class), app(ExpirePendingBookingAction::class));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->flag)->toBe('external_refund')
        ->and($booking->refresh()->status)->toBe(BookingStatus::Confirmed) // seat untouched
        ->and($session->refresh()->booked_count)->toBe(1);
});

it('auto-refunds an amount mismatch, frees the seat and promotes the waitlist', function () {
    $session = edgeSession(capacity: 1);
    $student = User::factory()->student()->create();
    $waiter = User::factory()->student()->create();
    [$booking, $checkoutId] = holdWithCheckout($student, $session);
    app(JoinWaitlistAction::class)->handle($waiter, $session->id);

    app(ConfirmPaidBookingAction::class)->handle(completedPayload($checkoutId, 999, 'pi_bad'));

    $payment = Payment::query()->where('booking_id', $booking->id)->sole();
    expect($booking->refresh()->status)->toBe(BookingStatus::Expired)
        ->and($payment->flag)->toBe('amount_mismatch')
        ->and($this->gateway->refundCalls)->toHaveCount(1)
        // The freed seat went to the waiter as a payable offer.
        ->and(Booking::query()->where('user_id', $waiter->id)->sole()->status)
        ->toBe(BookingStatus::PendingPayment);
});

it('expires paid-class waiters inside the minimum offer window but promotes free ones', function (int $price, string $expected) {
    $type = ClassType::factory()->create(['default_capacity' => 1]);
    $type->price_cents = $price;
    $type->save();
    $session = ClassSession::factory()
        ->forSchedule(Schedule::factory()->for($type)->create())
        ->create([
            'capacity' => 1,
            'starts_at' => now()->addMinutes(20), // inside the 30-min window
            'ends_at' => now()->addMinutes(80),
        ]);

    $holder = User::factory()->student()->create();
    $booking = app(BookSeatAction::class)->handle($holder, $session->id, (string) Str::uuid());
    if ($price > 0) {
        // Paid hold → make it a real seat first so the cancel frees it.
        app(CreateCheckoutForBookingAction::class)->handle($booking);
        $checkoutId = Payment::query()->sole()->stripe_checkout_session_id;
        app(ConfirmPaidBookingAction::class)->handle(completedPayload($checkoutId, $price, 'pi_win'));
    }
    $waiter = User::factory()->student()->create();
    $entry = app(JoinWaitlistAction::class)->handle($waiter, $session->id);

    app(CancelBookingAction::class)->handle($booking->refresh(), $holder);

    expect($entry->refresh()->status->value)->toBe($expected);

    if ($expected === 'expired') {
        // No one can be served a >=30-min offer: the seat is genuinely free.
        expect($session->refresh()->booked_count)->toBe(0);
    }
})->with([
    'paid class expires the queue' => [1500, 'expired'],
    'free class promotes to the end' => [0, 'promoted'],
]);

it('clamps a late waitlist offer checkout to the stripe 30-minute floor', function () {
    $session = edgeSession();
    $student = User::factory()->student()->create();
    app(BookSeatAction::class)->handle($student, $session->id, (string) Str::uuid());
    $booking = Booking::query()->sole();

    // Simulate a late offer: local deadline only 10 minutes out.
    $booking->payment_deadline_at = now()->addMinutes(10);
    $booking->save();

    app(CreateCheckoutForBookingAction::class)->handle($booking);

    $payload = array_values($this->gateway->sessions)[0]['payload'];
    expect($payload['expires_at'])->toBeGreaterThanOrEqual(now()->addMinutes(29)->getTimestamp());
});

it('lets the admin retry a failed refund through the same job', function () {
    $payment = Payment::factory()->refundFailed()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post("/admin/payments/{$payment->id}/retry-refund")
        ->assertRedirect();

    expect($this->gateway->refundCalls)->toHaveCount(1)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::RefundPending);

    $this->actingAs(User::factory()->student()->create())
        ->post("/admin/payments/{$payment->id}/retry-refund")
        ->assertForbidden();
});

it('shows flagged rows on the admin payments page', function () {
    Payment::factory()->succeeded()->flagged('external_refund')->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/payments')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Payments/Index')
            ->where('payments.0.flag', 'external_refund'));
});
