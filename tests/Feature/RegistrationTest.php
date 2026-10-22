<?php

use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200)
        ->assertSee('Every workday, accounted for.')
        ->assertSee('attachify-logo-title', false)
        ->assertDontSee('#6875F5', false)
        ->assertSee('Field supervisor')
        ->assertSee('Academic supervisor');
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('registration screen cannot be rendered if support is disabled', function () {
    $response = $this->get('/register');

    $response->assertStatus(404);
})->skip(function () {
    return Features::enabled(Features::registration());
}, 'Registration support is enabled.');

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'role' => 'student',
        'course' => 'Civil Engineering',
        'registration_number' => 'REG-2026-01',
        'student_number' => 'STU-2026-01',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
    ]);

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'role' => 'student',
        'course' => 'Civil Engineering',
        'registration_number' => 'REG-2026-01',
        'student_number' => 'STU-2026-01',
        'verification_status' => 'pending',
    ]);
    $response->assertRedirect(route('dashboard', absolute: false));
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Your attachment, one day at a time.');
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('field supervisors can register with a field supervisor account', function () {
    $this->post('/register', [
        'name' => 'Field Supervisor',
        'email' => 'field@example.com',
        'role' => 'field_supervisor',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('users', ['email' => 'field@example.com', 'role' => 'field_supervisor']);
    $this->get(route('dashboard'))->assertOk()->assertSee('Field supervisor');
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('academic supervisors can register with an academic supervisor account', function () {
    $this->post('/register', [
        'name' => 'Academic Supervisor',
        'email' => 'academic@school.example',
        'role' => 'academic_supervisor',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('users', ['email' => 'academic@school.example', 'role' => 'academic_supervisor']);
    $this->get(route('dashboard'))->assertOk()->assertSee('Academic supervisor');
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('registration rejects roles outside the supported account types', function () {
    $this->from('/register')->post('/register', [
        'name' => 'Unexpected Role',
        'email' => 'unexpected@example.com',
        'role' => 'administrator',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect('/register')->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('users', ['email' => 'unexpected@example.com']);
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('student registration requires course and both student identifiers', function () {
    $this->from('/register')->post('/register', [
        'name' => 'Missing Details',
        'email' => 'missing-details@example.com',
        'role' => 'student',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect('/register')
        ->assertSessionHasErrors(['course', 'registration_number', 'student_number']);

    $this->assertDatabaseMissing('users', ['email' => 'missing-details@example.com']);
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');
