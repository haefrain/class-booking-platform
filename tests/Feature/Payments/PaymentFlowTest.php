<?php

declare(strict_types=1);

use App\Actions\Bookings\BookSeatAction;
use App\Actions\Payments\CreateCheckoutForBookingAction;
use App\Actions\Waitlist\JoinWaitlistAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\StripeEvent;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use App\Payments\InvalidWebhookSignatureException;
use App\Payments\PaymentGateway;
use App\Payments\StripeGateway;
use App\Support\SeatInvariantAuditor;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Stripe\StripeClient;
use Tests\Fakes\FakePaymentGateway;

afterEach(fn () => SeatInvariantAuditor::assertAll());

beforeEach(function () {
    $this->gateway = new FakePaymentGateway;
    $this->swap(PaymentGateway::class, $this->gateway);
});

function paidSession(int $capacity = 3, int $priceCents = 1500): ClassSession
{
    $type = ClassType::factory()->create(['default_capacity' => $capacity]);
    $type->price_cents = $priceCents;
    $type->save();

    return ClassSession::factory()
        ->forSchedule(Schedule::factory()->for($type)->create())
        ->create(['capacity' => $capacity]);
}

function webhook(string $type, string $checkoutId, array $extra = []): TestResponse
{
    return test()->call('POST', '/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 'fake',
        'CONTENT_TYPE' => 'application/json',
    ], json_encode([
        'id' => 'evt_'.Str::random(12),
        'type' => $type,
        'data' => ['id' => $checkoutId, ...$extra],
    ]));
}

it('books a paid class into a pending hold and redirects to checkout', function () {
    $session = paidSession();
    $student = User::factory()->student()->create();

    $response = $this->actingAs($student)->post("/sessions/{$session->id}/bookings", [
        'idempotency_key' => (string) Str::uuid(),
    ]);

    // Inertia::location → plain 302 for non-Inertia requests (409 +
    // X-Inertia-Location only when the X-Inertia header travels).
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('checkout.fake');

    $booking = Booking::query()->sole();
    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->price_cents)->toBe(1500)
        ->and($booking->payment_deadline_at)->not->toBeNull()
        ->and($session->refresh()->booked_count)->toBe(1);

    $payment = Payment::query()->sole();
    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->amount_cents)->toBe(1500);
});

it('confirms the booking when the completed webhook arrives', function () {
    Notification::fake();
    $session = paidSession();
    $student = User::factory()->student()->create();
    app(BookSeatAction::class)->handle($student, $session->id, (string) Str::uuid());
    $booking = Booking::query()->sole();
    app(CreateCheckoutForBookingAction::class)->handle($booking);
    $payment = Payment::query()->sole();
    $checkoutId = $payment->stripe_checkout_session_id;
    $this->gateway->completeCheckout($checkoutId);

    webhook('checkout.session.completed', $checkoutId, [
        'amount_total' => 1500, 'currency' => 'usd', 'payment_intent' => 'pi_test_1',
    ])->assertOk();

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->payment_deadline_at)->toBeNull()
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->stripe_payment_intent_id)->toBe('pi_test_1')
        ->and($payment->paid_at)->not->toBeNull();

    Notification::assertSentTo($student, BookingConfirmedNotification::class);
});

it('deduplicates webhook replays via the event ledger', function () {
    $session = paidSession();
    $student = User::factory()->student()->create();
    app(BookSeatAction::class)->handle($student, $session->id, (string) Str::uuid());
    $booking = Booking::query()->sole();
    app(CreateCheckoutForBookingAction::class)->handle($booking);
    $checkoutId = Payment::query()->sole()->stripe_checkout_session_id;

    $payload = json_encode([
        'id' => 'evt_replayed', 'type' => 'checkout.session.completed',
        'data' => ['id' => $checkoutId, 'amount_total' => 1500, 'currency' => 'usd', 'payment_intent' => 'pi_1'],
    ]);
    $headers = ['HTTP_STRIPE_SIGNATURE' => 'fake', 'CONTENT_TYPE' => 'application/json'];

    $this->call('POST', '/stripe/webhook', [], [], [], $headers, $payload)->assertOk();
    $this->call('POST', '/stripe/webhook', [], [], [], $headers, $payload)->assertOk();

    expect(StripeEvent::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1);
});

it('ignores an expired webhook arriving after completion', function () {
    $session = paidSession();
    $student = User::factory()->student()->create();
    app(BookSeatAction::class)->handle($student, $session->id, (string) Str::uuid());
    $booking = Booking::query()->sole();
    app(CreateCheckoutForBookingAction::class)->handle($booking);
    $checkoutId = Payment::query()->sole()->stripe_checkout_session_id;

    webhook('checkout.session.completed', $checkoutId, [
        'amount_total' => 1500, 'currency' => 'usd', 'payment_intent' => 'pi_2',
    ]);
    webhook('checkout.session.expired', $checkoutId);

    // Out-of-order: the paid seat stays paid.
    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($session->refresh()->booked_count)->toBe(1);
});

it('expires the booking and flags the payment on amount mismatch', function () {
    $session = paidSession();
    $student = User::factory()->student()->create();
    app(BookSeatAction::class)->handle($student, $session->id, (string) Str::uuid());
    $booking = Booking::query()->sole();
    app(CreateCheckoutForBookingAction::class)->handle($booking);
    $payment = Payment::query()->sole();

    webhook('checkout.session.completed', $payment->stripe_checkout_session_id, [
        'amount_total' => 1, 'currency' => 'usd', 'payment_intent' => 'pi_bad',
    ]);

    // Full mismatch handling: never confirm, free the seat, auto-refund.
    expect($booking->refresh()->status)->toBe(BookingStatus::Expired)
        ->and($payment->refresh()->flag)->toBe('amount_mismatch')
        ->and($this->gateway->refundCalls)->toHaveCount(1);
});

it('lets the owner pay an existing hold and blocks everyone else', function () {
    $session = paidSession();
    $student = User::factory()->student()->create();
    app(BookSeatAction::class)->handle($student, $session->id, (string) Str::uuid());
    $booking = Booking::query()->sole();

    $this->actingAs(User::factory()->student()->create())
        ->post("/bookings/{$booking->id}/pay")
        ->assertForbidden();

    $response = $this->actingAs($student)->post("/bookings/{$booking->id}/pay");
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('checkout.fake');

    // Repeat click reuses the same open checkout — one payments row.
    $this->actingAs($student)->post("/bookings/{$booking->id}/pay");
    expect(Payment::query()->count())->toBe(1);
});

it('releases the seat when checkout creation fails at the gateway', function () {
    $session = paidSession(capacity: 1);
    $student = User::factory()->student()->create();
    $this->gateway->failNextWith('stripe is down');

    $this->actingAs($student)
        ->from("/sessions/{$session->id}")
        ->post("/sessions/{$session->id}/bookings", ['idempotency_key' => (string) Str::uuid()])
        ->assertRedirect("/sessions/{$session->id}")
        ->assertSessionHasErrors(['domain']);

    expect(Booking::query()->sole()->status)->toBe(BookingStatus::Expired)
        ->and($session->refresh()->booked_count)->toBe(0);
});

it('sweeps overdue holds stripe-first and promotes the waitlist', function () {
    $session = paidSession(capacity: 1);
    $holder = User::factory()->student()->create();
    $waiter = User::factory()->student()->create();
    app(BookSeatAction::class)->handle($holder, $session->id, (string) Str::uuid());
    $booking = Booking::query()->sole();
    app(CreateCheckoutForBookingAction::class)->handle($booking);
    app(JoinWaitlistAction::class)->handle($waiter, $session->id);

    $this->travel(31)->minutes();
    $this->artisan('bookings:expire-pending')->assertSuccessful();

    $checkoutId = Payment::query()->sole()->stripe_checkout_session_id;
    expect($this->gateway->expireCalls)->toContain($checkoutId)
        ->and($booking->refresh()->status)->toBe(BookingStatus::Expired)
        ->and(Payment::query()->sole()->status)->toBe(PaymentStatus::Canceled);

    // The freed seat went to the waiter as a payable offer (paid class).
    $offer = Booking::query()->where('user_id', $waiter->id)->sole();
    expect($offer->status)->toBe(BookingStatus::PendingPayment)
        ->and($offer->source)->toBe('waitlist');
});

it('reconciles a paid-but-webhookless checkout instead of expiring it', function () {
    $session = paidSession(capacity: 1);
    $student = User::factory()->student()->create();
    app(BookSeatAction::class)->handle($student, $session->id, (string) Str::uuid());
    $booking = Booking::query()->sole();
    app(CreateCheckoutForBookingAction::class)->handle($booking);
    $checkoutId = Payment::query()->sole()->stripe_checkout_session_id;

    // Paid on Stripe's side, but the webhook never arrived.
    $this->gateway->completeCheckout($checkoutId, 1500, 'pi_reconciled');

    $this->travel(31)->minutes();
    $this->artisan('bookings:expire-pending')->assertSuccessful();

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and(Payment::query()->sole()->stripe_payment_intent_id)->toBe('pi_reconciled');
});

it('skips a hold whose gateway errored and retries next tick', function () {
    $session = paidSession();
    $student = User::factory()->student()->create();
    app(BookSeatAction::class)->handle($student, $session->id, (string) Str::uuid());
    $booking = Booking::query()->sole();
    app(CreateCheckoutForBookingAction::class)->handle($booking);

    $this->travel(31)->minutes();
    $this->gateway->failNextWith('rate limited');
    $this->artisan('bookings:expire-pending')->assertSuccessful();

    // Never expire locally while the checkout may still be payable (S5).
    expect($booking->refresh()->status)->toBe(BookingStatus::PendingPayment);

    $this->artisan('bookings:expire-pending')->assertSuccessful();
    expect($booking->refresh()->status)->toBe(BookingStatus::Expired);
});

it('rejects the fourth concurrent pending hold', function () {
    $student = User::factory()->student()->create();
    foreach (range(1, 3) as $i) {
        app(BookSeatAction::class)->handle($student, paidSession()->id, (string) Str::uuid());
    }

    $this->actingAs($student)
        ->from('/catalog')
        ->post('/sessions/'.paidSession()->id.'/bookings', ['idempotency_key' => (string) Str::uuid()])
        ->assertRedirect('/catalog')
        ->assertSessionHasErrors(['domain']);
});

it('verifies real webhook signatures through the stripe gateway', function () {
    $secret = 'whsec_test_secret';
    $gateway = new StripeGateway(new StripeClient('sk_test_x'), $secret);
    $payload = (string) json_encode([
        'id' => 'evt_hmac', 'object' => 'event', 'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_x', 'object' => 'checkout.session']],
    ]);
    $timestamp = time();
    $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    $event = $gateway->parseWebhookEvent($payload, $signature);
    expect($event['id'])->toBe('evt_hmac')
        ->and($event['type'])->toBe('checkout.session.completed');

    $gateway->parseWebhookEvent($payload, 't='.$timestamp.',v1=forged');
})->throws(InvalidWebhookSignatureException::class);
