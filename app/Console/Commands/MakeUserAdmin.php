<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeUserAdmin extends Command
{
    protected $signature = 'sims:make-admin {email : Email address for the first administrator}';

    protected $description = 'Create or promote an administrator account for SIMS';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $validated = Validator::make([
                'email' => $this->argument('email'),
                'name' => $this->ask('Administrator name'),
                'password' => $this->secret('Administrator password'),
            ], [
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:12'],
            ])->validate();

            $user = new User;
            $user->forceFill([
                ...$validated,
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
                'role' => 'admin',
                'verification_status' => 'approved',
                'verified_at' => now(),
            ])->save();

            $this->info("{$user->email} is now an approved SIMS administrator.");

            return self::SUCCESS;
        }

        if (! $this->confirm("Promote {$user->email} to administrator and set a new password?")) {
            $this->line('No changes were made.');

            return self::SUCCESS;
        }

        $validated = Validator::make([
            'password' => $this->secret('New administrator password'),
            'password_confirmation' => $this->secret('Confirm new administrator password'),
        ], [
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'role' => 'admin',
            'verification_status' => 'approved',
            'verified_at' => now(),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'is_active' => true,
            'must_change_password' => false,
        ])->save();

        $this->info("{$user->email} is now an approved SIMS administrator.");

        return self::SUCCESS;
    }
}
