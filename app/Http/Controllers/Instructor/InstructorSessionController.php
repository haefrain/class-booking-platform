<?php

declare(strict_types=1);

namespace App\Http\Controllers\Instructor;

use App\Enums\BookingStatus;
use App\Enums\WaitlistStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClassSessionResource;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InstructorSessionController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $sessions = ClassSession::query()
            ->upcoming()
            ->where('instructor_id', $user->getKey())
            ->with(['classType', 'instructor'])
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('Instructor/Sessions/Index', [
            'sessions' => ClassSessionResource::collection($sessions)->resolve(),
        ]);
    }

    public function show(Request $request, ClassSession $session): Response
    {
        $this->authorize('viewRoster', $session);

        $session->load(['classType', 'instructor']);

        return Inertia::render('Instructor/Sessions/Show', [
            'session' => ClassSessionResource::make($session)->resolve(),
            'roster' => $session->bookings()
                ->whereIn('status', [BookingStatus::PendingPayment, BookingStatus::Confirmed])
                ->with('user')
                ->orderBy('id')
                ->get()
                ->map(fn (Booking $booking) => [
                    'id' => $booking->id,
                    'name' => $booking->user?->name,
                    'status' => $booking->status->value,
                    'source' => $booking->source,
                ]),
            'waitlist' => $session->waitlistEntries()
                ->where('status', WaitlistStatus::Waiting)
                ->with('user')
                ->orderBy('id')
                ->get()
                ->map(fn (WaitlistEntry $entry) => [
                    'id' => $entry->id,
                    'name' => $entry->user?->name,
                ]),
        ]);
    }
}
