<?php

declare(strict_types=1);

use App\Models\User;

it('redirects each role from the dashboard to its home', function (string $factoryState, string $destination) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect($destination);
})->with([
    'admin' => ['admin', '/admin'],
    'instructor' => ['instructor', '/instructor/sessions'],
    'student' => ['student', '/catalog'],
]);

it('sends guests from the dashboard to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('serves the catalog publicly', function () {
    $this->get('/catalog')->assertOk();

    $student = User::factory()->student()->create();
    $this->actingAs($student)->get('/catalog')->assertOk();
});

it('gates the admin section to admins only', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    $this->actingAs(User::factory()->student()->create())->get('/admin')->assertForbidden();
    $this->actingAs(User::factory()->instructor()->create())->get('/admin')->assertForbidden();
});

it('gates the instructor section to instructors only', function () {
    $this->actingAs(User::factory()->instructor()->create())->get('/instructor/sessions')->assertOk();
    $this->actingAs(User::factory()->student()->create())->get('/instructor/sessions')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get('/instructor/sessions')->assertForbidden();
});

it('shares the authenticated user role with every Inertia page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertInertia(fn ($page) => $page->where('auth.user.role', 'admin'));
});
