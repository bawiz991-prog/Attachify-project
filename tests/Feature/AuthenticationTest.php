<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create([
        'email' => 'student@mubs.ac.ug',
        'role' => 'student',
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('seeded students must change the default password before using the dashboard', function () {
    $this->seed(\Database\Seeders\UserSeeder::class);

    $student = User::query()->where('email', '24007001@mubs.ac.ug')->firstOrFail();

    expect($student->must_change_password)->toBeTrue();

    $this->post('/login', [
        'email' => $student->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard'))
        ->assertRedirect(route('account.password.edit'));

    $this->get(route('account.password.edit'))
        ->assertOk()
        ->assertSee('Set a private password');

    $this->post(route('account.password.update'), [
        'password' => 'A-new-private-password-123!',
        'password_confirmation' => 'A-new-private-password-123!',
    ])->assertRedirect(route('dashboard'));

    expect($student->fresh()->must_change_password)->toBeFalse();
    $this->get(route('dashboard'))->assertOk();
});

test('users cannot authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('student accounts must use a university email address at sign in', function () {
    User::factory()->create([
        'email' => 'student@example.com',
        'role' => 'student',
    ]);

    $this->post('/login', [
        'email' => 'student@example.com',
        'password' => 'password',
    ]);

    $this->assertGuest();
});
