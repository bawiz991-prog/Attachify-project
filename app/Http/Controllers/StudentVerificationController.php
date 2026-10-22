<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentVerificationController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'student', 403);

        $validated = $request->validate([
            'course' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:100', Rule::unique('users')->ignore($request->user()->id)],
            'student_number' => ['required', 'string', 'max:100', Rule::unique('users')->ignore($request->user()->id)],
        ]);

        $user = $request->user();
        $changed = collect($validated)->contains(fn ($value, $key) => $value !== $user->{$key});
        $user->forceFill($validated);

        if ($changed) {
            $user->forceFill([
                'verification_status' => 'pending',
                'verified_by_id' => null,
                'verified_at' => null,
                'verification_notes' => null,
            ]);
        }

        $user->save();

        if ($changed) {
            User::query()->where('role', 'admin')->each(fn (User $admin) => $admin->notify(new WorkspaceNotice(
                'Student details updated',
                $user->name.' updated their course and student identifiers for review.',
            )));
        }

        return back()->with('status', $changed
            ? 'Your student details were updated and sent for administrator review.'
            : 'Your student details are up to date.');
    }
}
