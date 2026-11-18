<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('promoting an existing user also resets their password', function () {
    $user = User::factory()->create([
        'email' => 'mubsadmin@mubs.ac.ug',
        'role' => 'coordinator',
        'must_change_password' => true,
    ]);

    $this->artisan('sims:make-admin mubsadmin@mubs.ac.ug')
        ->expectsConfirmation('Promote mubsadmin@mubs.ac.ug to administrator and set a new password?', 'yes')
        ->expectsQuestion('New administrator password', 'secure-test-password-123')
        ->expectsQuestion('Confirm new administrator password', 'secure-test-password-123')
        ->expectsOutput('mubsadmin@mubs.ac.ug is now an approved SIMS administrator.')
        ->assertExitCode(0);

    $user->refresh();

    expect($user->role)->toBe('admin')
        ->and($user->is_active)->toBeTrue()
        ->and($user->must_change_password)->toBeFalse()
        ->and(Hash::check('secure-test-password-123', $user->password))->toBeTrue();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'secure-test-password-123',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});
