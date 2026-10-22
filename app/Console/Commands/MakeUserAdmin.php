<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    protected $signature = 'attachify:make-admin {email : Email address of the existing account}';

    protected $description = 'Promote an existing account to the Attachify administrator role';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account exists for that email. Register the account first.');

            return self::FAILURE;
        }

        if (! $this->confirm("Promote {$user->email} to administrator?")) {
            $this->line('No changes were made.');

            return self::SUCCESS;
        }

        $user->forceFill([
            'role' => 'admin',
            'verification_status' => 'approved',
            'verified_at' => now(),
        ])->save();

        $this->info("{$user->email} is now an approved Attachify administrator.");

        return self::SUCCESS;
    }
}
