<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\WaitlistStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

        $heartbeat = Cache::get('scheduler.heartbeat');
        $weekAhead = [now(), now()->addDays(7)];

        return Inertia::render('Admin/Dashboard', [
            'scheduler' => [
                'last_heartbeat' => $heartbeat,
                'healthy' => $heartbeat !== null
                    && CarbonImmutable::parse((string) $heartbeat)->gt(now()->subMinutes(3)),
            ],
            'kpis' => [
                'sessions_next_7d' => ClassSession::query()->upcoming()
                    ->whereBetween('starts_at', $weekAhead)->count(),
                'confirmed_next_7d' => Booking::query()
                    ->where('status', BookingStatus::Confirmed)
                    ->whereHas('session', fn ($q) => $q->whereBetween('starts_at', $weekAhead))
                    ->count(),
                'waiting_now' => WaitlistEntry::query()->where('status', WaitlistStatus::Waiting)->count(),
                'collected_cents' => (int) Payment::query()
                    ->whereIn('status', [PaymentStatus::Succeeded, PaymentStatus::RefundPending])
                    ->sum('amount_cents'),
            ],
            // Heavier aggregate: streams in after first paint (skeleton shows).
            'occupancy' => Inertia::defer(fn () => ClassSession::query()->upcoming()
                ->whereBetween('starts_at', [now(), now()->addDays(7)])
                ->with('classType')
                ->orderBy('starts_at')
                ->limit(10)
                ->get()
                ->map(fn (ClassSession $session) => [
                    'id' => $session->id,
                    'name' => $session->classType?->name,
                    'starts_at' => $session->starts_at->toIso8601ZuluString(),
                    'booked' => $session->booked_count,
                    'capacity' => $session->capacity,
                ])
                ->values()
                ->all()),
        ]);
    }
}
