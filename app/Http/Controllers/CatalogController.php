<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\IndexCatalogRequest;
use App\Http\Resources\ClassSessionResource;
use App\Models\ClassSession;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(IndexCatalogRequest $request): Response
    {
        $timezone = (string) config('academy.timezone');
        $week = $request->validated('week');

        $weekStart = ($week === null
            ? CarbonImmutable::now($timezone)
            : CarbonImmutable::parse((string) $week, $timezone)
        )->startOfWeek();

        $sessions = ClassSession::query()
            ->upcoming()
            ->whereBetween('starts_at', [$weekStart->utc(), $weekStart->endOfWeek()->utc()])
            ->with(['classType', 'instructor'])
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('Catalog/Index', [
            'sessions' => ClassSessionResource::collection($sessions)->resolve(),
            'week' => [
                'start' => $weekStart->toDateString(),
                'prev' => $weekStart->subWeek()->toDateString(),
                'next' => $weekStart->addWeek()->toDateString(),
            ],
        ]);
    }
}
