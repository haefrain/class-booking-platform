<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Schedules\CreateScheduleAction;
use App\Actions\Schedules\RegenerateFutureSessions;
use App\Actions\Schedules\UpdateScheduleAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreScheduleRequest;
use App\Http\Requests\Admin\UpdateScheduleRequest;
use App\Models\ClassType;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Schedule::class);

        return Inertia::render('Admin/Schedules/Index', [
            'schedules' => Schedule::query()
                ->with(['classType', 'instructor'])
                ->orderBy('weekday')->orderBy('start_time')
                ->get()
                ->map(fn (Schedule $schedule) => [
                    'id' => $schedule->id,
                    'weekday' => $schedule->weekday,
                    'start_time' => substr($schedule->start_time, 0, 5),
                    'duration_minutes' => $schedule->duration_minutes,
                    'capacity' => $schedule->capacity,
                    'starts_on' => $schedule->starts_on->toDateString(),
                    'ends_on' => $schedule->ends_on?->toDateString(),
                    'is_active' => $schedule->is_active,
                    'class_type' => ['id' => $schedule->classType?->id, 'name' => $schedule->classType?->name],
                    'instructor' => ['id' => $schedule->instructor?->id, 'name' => $schedule->instructor?->name],
                ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Schedule::class);

        return Inertia::render('Admin/Schedules/Create', $this->formOptions());
    }

    public function store(StoreScheduleRequest $request, CreateScheduleAction $action): RedirectResponse
    {
        $this->authorize('create', Schedule::class);

        $action->handle($request->validated());

        return redirect('/admin/schedules')->with('success', 'Schedule created — sessions generated.');
    }

    public function edit(Schedule $schedule): Response
    {
        $this->authorize('update', $schedule);

        return Inertia::render('Admin/Schedules/Edit', [
            ...$this->formOptions(),
            'schedule' => [
                'id' => $schedule->id,
                'class_type_id' => $schedule->class_type_id,
                'instructor_id' => $schedule->instructor_id,
                'weekday' => $schedule->weekday,
                'start_time' => substr($schedule->start_time, 0, 5),
                'duration_minutes' => $schedule->duration_minutes,
                'capacity' => $schedule->capacity,
                'starts_on' => $schedule->starts_on->toDateString(),
                'ends_on' => $schedule->ends_on?->toDateString(),
                'is_active' => $schedule->is_active,
            ],
        ]);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule, UpdateScheduleAction $action): RedirectResponse
    {
        $this->authorize('update', $schedule);

        $action->handle($schedule, $request->validated());

        return redirect('/admin/schedules')
            ->with('success', 'Schedule updated. Existing sessions were not changed — regenerate to apply.');
    }

    public function regenerate(Schedule $schedule, RegenerateFutureSessions $action): RedirectResponse
    {
        $this->authorize('update', $schedule);

        $inserted = $action->handle($schedule);

        return back()->with('success', "Future sessions regenerated ({$inserted} created).");
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'classTypes' => ClassType::query()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ClassType $type) => ['id' => $type->id, 'name' => $type->name]),
            'instructors' => User::query()->where('role', UserRole::Instructor)->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name]),
        ];
    }
}
