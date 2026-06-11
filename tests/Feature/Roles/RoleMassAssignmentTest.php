<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

it('ignores a smuggled role field on profile update', function () {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->patch('/settings/profile', [
            'name' => 'Still A Student',
            'email' => $student->email,
            'role' => 'admin', // smuggled — role is never fillable
        ])
        ->assertRedirect();

    expect($student->refresh())
        ->role->toBe(UserRole::Student)
        ->name->toBe('Still A Student');
});

it('never exposes role as fillable on the user model', function () {
    $user = new User;

    expect($user->isFillable('role'))->toBeFalse();
});
