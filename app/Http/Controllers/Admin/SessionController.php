<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\IndexCatalogRequest;
use App\Models\ClassSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    /** Week view with occupancy and the cancel/capacity controls. */
    public function index(IndexCatalogRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

        $timezone = (string) config('academy.timezone');
        $week = $request->validated('week');

        $weekStart = ($week === null
            ? CarbonImmutable::now($timezone)
            : CarbonImmutable::parse((string) $week, $timezone)
        )->startOfWeek();

        $sessions = ClassSession::query()
            ->whereBetween('starts_at', [$weekStart->utc(), $weekStart->endOfWeek()->utc()])
            ->with(['classType', 'instructor'])
            ->withCount(['waitlistEntries as waiting_count' => fn ($q) => $q->where('status', 'waiting')])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (ClassSession $session) => [
                'id' => $session->id,
                'name' => $session->classType?->name,
                'instructor' => $session->instructor?->name,
                'starts_at' => $session->starts_at->toIso8601ZuluString(),
                'status' => $session->status->value,
                'booked' => $session->booked_count,
                'capacity' => $session->capacity,
                'waiting' => (int) $session->getAttribute('waiting_count'),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Sessions/Index', [
            'sessions' => $sessions,
            'week' => [
                'start' => $weekStart->toDateString(),
                'prev' => $weekStart->subWeek()->toDateString(),
                'next' => $weekStart->addWeek()->toDateString(),
            ],
        ]);
    }
}
