<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Program;
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
            'faculty' => ['required', Rule::in(config('faculties'))],
            'registration_number' => ['required', 'string', 'max:100', Rule::unique('users')->ignore($request->user()->id)],
            'student_number' => ['required', 'string', 'max:100', Rule::unique('users')->ignore($request->user()->id)],
        ]);

        $user = $request->user();
        $program = Program::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['course'])])
            ->first();

        if (! $program) {
            return back()->withErrors(['course' => 'Choose a program from the official SIMS program list.'])->withInput();
        }

        $validated['course'] = $program->name;
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
            User::query()
                ->where('faculty', $user->faculty)
                ->whereIn('role', ['faculty_handler', 'faculty_dean'])
                ->where('is_active', true)
                ->each(fn (User $facultyStaff) => $facultyStaff->notify(new WorkspaceNotice(
                    'Student details awaiting faculty review',
                    $user->name.' submitted their course, faculty, and student identifiers for review.',
                )));
        }

        return back()->with('status', $changed
            ? 'Your student details were updated and sent to your faculty for review.'
            : 'Your student details are up to date.');
    }
}
