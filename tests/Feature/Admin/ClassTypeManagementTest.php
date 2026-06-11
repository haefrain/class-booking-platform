<?php

declare(strict_types=1);

use App\Models\ClassType;
use App\Models\User;

it('lists class types for the admin with the expected shape', function () {
    ClassType::factory()->count(2)->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/class-types')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/ClassTypes/Index')
            ->has('classTypes', 2, fn ($type) => $type
                ->hasAll(['id', 'name', 'slug', 'duration_minutes', 'default_capacity', 'price_cents', 'cancellation_deadline_hours', 'is_active'])
            )
        );
});

it('creates a class type with admin-assigned price', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->post('/admin/class-types', [
        'name' => 'Hot Yoga',
        'description' => 'Sweaty.',
        'duration_minutes' => 60,
        'default_capacity' => 15,
        'price_cents' => 1800,
        'cancellation_deadline_hours' => 12,
    ])->assertRedirect('/admin/class-types');

    $this->assertDatabaseHas('class_types', [
        'name' => 'Hot Yoga',
        'slug' => 'hot-yoga',
        'price_cents' => 1800,
    ]);
});

it('updates a class type without touching its slug', function () {
    $type = ClassType::factory()->create(['name' => 'Pilates', 'slug' => 'pilates']);
    $this->actingAs(User::factory()->admin()->create());

    $this->put("/admin/class-types/{$type->id}", [
        'name' => 'Pilates Reformer',
        'duration_minutes' => 45,
        'default_capacity' => 8,
        'price_cents' => 2500,
        'cancellation_deadline_hours' => 24,
        'is_active' => false,
    ])->assertRedirect('/admin/class-types');

    expect($type->refresh())
        ->name->toBe('Pilates Reformer')
        ->slug->toBe('pilates')
        ->price_cents->toBe(2500)
        ->is_active->toBeFalse();
});

it('validates the class type payload', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->post('/admin/class-types', [
        'name' => '',
        'duration_minutes' => 0,
        'default_capacity' => 0,
        'price_cents' => -5,
    ])->assertSessionHasErrors(['name', 'duration_minutes', 'default_capacity', 'price_cents']);
});

it('forbids non-admins from managing class types', function (string $state) {
    $type = ClassType::factory()->create();
    $this->actingAs(User::factory()->{$state}()->create());

    $this->get('/admin/class-types')->assertForbidden();
    $this->post('/admin/class-types', [])->assertForbidden();
    $this->put("/admin/class-types/{$type->id}", [])->assertForbidden();
})->with(['student', 'instructor']);
