<?php

namespace App\Http\Controllers;

use App\Models\FieldSupervisorInvitation;
use App\Models\User;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class FieldSupervisorInvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->findInvitation($token);
        $existingUser = User::query()->where('email', $invitation->email)->first();
        abort_if($existingUser && $existingUser->role !== 'field_supervisor', 409, 'This email is already used by another account type.');

        return view('invitations.field-supervisor', [
            'invitation' => $invitation->load('placement.student'),
            'existingUser' => $existingUser,
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findInvitation($token);
        $existingUser = User::query()->where('email', $invitation->email)->first();
        abort_if($existingUser && $existingUser->role !== 'field_supervisor', 409, 'This email is already used by another account type.');

        $validated = $request->validate($existingUser ? [] : [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        DB::transaction(function () use ($invitation, $existingUser, $validated) {
            $invitation->refresh();
            abort_if($invitation->accepted_at || $invitation->expires_at->isPast(), 410, 'This invitation has expired or was already accepted.');

            $user = $existingUser ?? new User;
            $user->forceFill([
                ...($existingUser ? [] : [
                    'name' => $validated['name'],
                    'password' => $validated['password'],
                ]),
                'email' => $invitation->email,
                'email_verified_at' => now(),
                'role' => 'field_supervisor',
                'verification_status' => 'approved',
                'verified_by_id' => $invitation->invited_by_id,
                'verified_at' => now(),
                'is_active' => true,
            ])->save();

            $invitation->placement->fieldSupervisor()->associate($user);
            $invitation->placement->save();
            $invitation->forceFill(['accepted_at' => now()])->save();
        });

        $invitation->placement->student->notify(new WorkspaceNotice(
            'Field supervisor connected',
            'Your field supervisor accepted the invitation for '.$invitation->placement->company_name.'.',
            ['placement_id' => $invitation->placement_id],
        ));

        return redirect()->route('login')->with('status', 'Your field supervisor account is active. Sign in to view the assigned placement.');
    }

    private function findInvitation(string $token): FieldSupervisorInvitation
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1, 404);

        return FieldSupervisorInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();
    }
}
