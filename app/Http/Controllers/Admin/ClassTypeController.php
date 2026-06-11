<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\ClassTypes\CreateClassTypeAction;
use App\Actions\ClassTypes\UpdateClassTypeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClassTypeRequest;
use App\Http\Requests\Admin\UpdateClassTypeRequest;
use App\Models\ClassType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClassTypeController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ClassType::class);

        return Inertia::render('Admin/ClassTypes/Index', [
            'classTypes' => ClassType::query()
                ->orderBy('name')
                ->get()
                ->map(fn (ClassType $type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'slug' => $type->slug,
                    'duration_minutes' => $type->duration_minutes,
                    'default_capacity' => $type->default_capacity,
                    'price_cents' => $type->price_cents,
                    'cancellation_deadline_hours' => $type->cancellation_deadline_hours,
                    'is_active' => $type->is_active,
                ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ClassType::class);

        return Inertia::render('Admin/ClassTypes/Create');
    }

    public function store(StoreClassTypeRequest $request, CreateClassTypeAction $action): RedirectResponse
    {
        $this->authorize('create', ClassType::class);

        $action->handle($request->validated());

        return redirect('/admin/class-types')->with('success', 'Class type created.');
    }

    public function edit(ClassType $classType): Response
    {
        $this->authorize('update', $classType);

        return Inertia::render('Admin/ClassTypes/Edit', [
            'classType' => [
                'id' => $classType->id,
                'name' => $classType->name,
                'description' => $classType->description,
                'duration_minutes' => $classType->duration_minutes,
                'default_capacity' => $classType->default_capacity,
                'price_cents' => $classType->price_cents,
                'cancellation_deadline_hours' => $classType->cancellation_deadline_hours,
                'is_active' => $classType->is_active,
            ],
        ]);
    }

    public function update(UpdateClassTypeRequest $request, ClassType $classType, UpdateClassTypeAction $action): RedirectResponse
    {
        $this->authorize('update', $classType);

        $action->handle($classType, $request->validated());

        return redirect('/admin/class-types')->with('success', 'Class type updated.');
    }
}
