<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Bookings\BookSeatAction;
use App\Actions\Bookings\CancelBookingAction;
use App\Enums\BookingStatus;
use App\Enums\WaitlistStatus;
use App\Exceptions\SessionNotBookableException;
use App\Http\Resources\ClassSessionResource;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function store(Request $request, ClassSession $session, BookSeatAction $action): RedirectResponse
    {
        $this->authorize('create', [Booking::class, $session]);

        $validated = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
        ]);

        /** @var User $user */
        $user = $request->user();

        // Paid checkout ships with the payments milestone.
        if (! $session->classType->isFree()) {
            throw new SessionNotBookableException('Online payment for this class is coming soon.');
        }

        $booking = $action->handle($user, $session->id, (string) $validated['idempotency_key']);

        return redirect("/bookings/{$booking->id}/confirmation");
    }

    public function confirmation(Request $request, Booking $booking): Response
    {
        $this->authorize('view', $booking); // owner or admin — H4

        $booking->load(['session.classType', 'session.instructor']);

        return Inertia::render('Bookings/Confirmation', [
            'booking' => [
                'id' => $booking->id,
                'status' => $booking->status->value,
                'source' => $booking->source,
                'price_cents' => $booking->price_cents,
            ],
            'session' => ClassSessionResource::make($booking->session)->resolve(),
        ]);
    }

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'tab' => ['sometimes', 'in:upcoming,past,waitlist'],
        ]);
        $tab = $validated['tab'] ?? 'upcoming';

        $bookings = Booking::query()
            ->where('user_id', $user->getKey())
            ->with(['session.classType', 'session.instructor'])
            ->when($tab === 'upcoming', fn ($q) => $q
                ->whereIn('status', [BookingStatus::PendingPayment, BookingStatus::Confirmed])
                ->whereHas('session', fn ($s) => $s->where('starts_at', '>', now())))
            ->when($tab === 'past', fn ($q) => $q
                ->where(fn ($w) => $w
                    ->whereIn('status', [BookingStatus::Cancelled, BookingStatus::Expired])
                    ->orWhereHas('session', fn ($s) => $s->where('starts_at', '<=', now()))))
            ->latest('id')
            ->get()
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'status' => $booking->status->value,
                'source' => $booking->source,
                'cancellation_kind' => $booking->cancellation_kind?->value,
                'session' => ClassSessionResource::make($booking->session)->resolve(),
            ]);

        $waitlist = $user->waitlistEntries()
            ->where('status', WaitlistStatus::Waiting)
            ->with(['session.classType', 'session.instructor'])
            ->get()
            ->map(fn (WaitlistEntry $entry) => [
                'id' => $entry->id,
                'session' => ClassSessionResource::make($entry->session)->resolve(),
            ]);

        return Inertia::render('Bookings/Index', [
            'tab' => $tab,
            'bookings' => $bookings,
            'waitlist' => $waitlist,
        ]);
    }

    public function destroy(Request $request, Booking $booking, CancelBookingAction $action): RedirectResponse
    {
        $this->authorize('cancel', $booking);

        /** @var User $user */
        $user = $request->user();

        $action->handle($booking, $user);

        return back()->with('success', 'Booking cancelled — your seat was released.');
    }
}
