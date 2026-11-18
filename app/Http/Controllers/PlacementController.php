<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Models\User;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlacementController extends Controller
{
    public function connectFieldSupervisor(Request $request, Placement $placement): RedirectResponse
    {
        abort_unless($placement->student_id === $request->user()->id, 403);
        abort_unless($request->user()->isApproved(), 403, 'Your account must be approved before connecting a supervisor.');

        $validated = $request->validate([
            'field_supervisor_email' => ['required', 'email', 'max:255'],
        ]);

        abort_if($placement->field_supervisor_id, 409, 'A field supervisor is already connected to this placement.');
        abort_if($placement->field_supervisor_email, 409, 'A field-supervisor invitation has already been requested.');
        abort_unless(in_array($placement->faculty_name, config('faculties'), true), 409, 'The placement must be assigned to a faculty before requesting a field-supervisor invitation.');

        $facultyStaff = User::query()
            ->where('faculty', $placement->faculty_name)
            ->whereIn('role', ['faculty_handler', 'faculty_dean'])
            ->where('is_active', true)
            ->get();
        abort_if($facultyStaff->isEmpty(), 409, 'There are no active faculty staff to receive the invitation request.');

        $email = strtolower($validated['field_supervisor_email']);
        $placement->forceFill([
            'field_supervisor_email' => $email,
        ])->save();

        $facultyStaff->each(fn (User $facultyUser) => $facultyUser->notify(new WorkspaceNotice(
            'Field supervisor invitation requested',
            $request->user()->name.' requested an invitation for '.$email.'.',
            ['placement_id' => $placement->id],
        )));

        return redirect()->route('dashboard')->with('status', 'Your field supervisor\'s email has been sent to your faculty. They will send an invitation to that address.');
    }

    public function connectAcademicSupervisor(Request $request, Placement $placement): RedirectResponse
    {
        abort_unless($placement->student_id === $request->user()->id, 403);
        abort_unless($request->user()->isApproved(), 403, 'Your account must be approved before connecting a supervisor.');

        $validated = $request->validate([
            'academic_supervisor_email' => ['required', 'email', 'max:255'],
        ]);

        $supervisor = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($validated['academic_supervisor_email'])])
            ->where('role', 'academic_supervisor')
            ->where('verification_status', 'approved')
            ->first();

        if (! $supervisor) {
            return back()->withErrors([
                'academic_supervisor_email' => 'No academic supervisor account uses this email. Ask your assigned supervisor to register first.',
            ])->withInput();
        }

        $placement->academicSupervisor()->associate($supervisor);
        $placement->save();

        return redirect()->route('dashboard')->with('status', 'Your academic supervisor has been connected.');
    }

    public function confirm(Request $request, Placement $placement): RedirectResponse
    {
        abort_unless($placement->student_id === $request->user()->id, 403);
        abort_if($placement->faculty_review_status !== null, 409, 'Respond to the faculty verification request instead.');
        abort_unless($request->user()->isApproved(), 403);
        abort_if($placement->document_status === 'approved', 409, 'This placement has already been approved.');

        $placement->forceFill(['student_confirmed_at' => now()])->save();

        User::query()->where('role', 'admin')->each(fn (User $admin) => $admin->notify(new \App\Notifications\WorkspaceNotice(
            'Student confirmed internship place',
            $request->user()->name.' confirmed '.$placement->company_name.' as their internship placement.',
            ['placement_id' => $placement->id],
        )));

        return redirect()->route('dashboard')->with('status', 'Your internship place is confirmed and ready for administrator review.');
    }

    public function uploadDocuments(Request $request, Placement $placement): RedirectResponse
    {
        abort_unless($placement->student_id === $request->user()->id, 403);
        abort_if(
            $placement->faculty_review_status !== null
                && $placement->faculty_review_status !== 'correction_requested',
            409,
            'Supporting letters can only be replaced after the faculty requests a correction.'
        );
        abort_unless($placement->document_status !== 'approved', 409, 'Approved placement documents cannot be replaced.');

        $validated = $request->validate([
            'acceptance_document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'placement_document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $acceptancePath = $request->file('acceptance_document')->store('acceptance-letters');
        if (! $acceptancePath) {
            throw new \RuntimeException('The company acceptance letter could not be stored. Please try again.');
        }

        $placementPath = $request->file('placement_document')->store('placement-letters');
        if (! $placementPath) {
            throw new \RuntimeException('The faculty placement letter could not be stored. Please try again.');
        }

        $placement->forceFill([
            'acceptance_document_path' => $acceptancePath,
            'placement_document_path' => $placementPath,
            'document_status' => 'pending',
            'document_reviewed_by_id' => null,
            'document_reviewed_at' => null,
            'document_review_notes' => null,
        ])->save();

        return redirect()->route('dashboard')->with('status', 'Both placement letters were uploaded for administrator review.');
    }

    public function assignAcademicSupervisor(Request $request, Placement $placement): RedirectResponse
    {
        $validated = $request->validate([
            'academic_supervisor_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $supervisor = User::query()->findOrFail($validated['academic_supervisor_id']);
        abort_unless(
            $supervisor->role === 'academic_supervisor'
                && $supervisor->isApproved()
                && $supervisor->hasVerifiedEmail(),
            422,
            'Choose an activated academic supervisor account.'
        );
        abort_unless(
            $supervisor->course === $placement->student->course,
            422,
            'The academic supervisor must be assigned to the student’s program.'
        );

        DB::transaction(function () use ($placement, $supervisor) {
            User::query()->whereKey($supervisor->id)->lockForUpdate()->firstOrFail();

            if ($placement->academic_supervisor_id !== $supervisor->id) {
                $assignedStudents = Placement::query()
                    ->where('academic_supervisor_id', $supervisor->id)
                    ->where('id', '!=', $placement->id)
                    ->whereHas('student', fn ($query) => $query->where('course', $placement->student->course))
                    ->count();

                if ($assignedStudents >= 10) {
                    throw ValidationException::withMessages([
                        'academic_supervisor_id' => 'This academic supervisor already has the maximum of 10 students in this program.',
                    ]);
                }
            }

            $placement->academicSupervisor()->associate($supervisor);
            $placement->save();
        });

        $placement->student->notify(new \App\Notifications\WorkspaceNotice(
            'Academic supervisor assigned',
            $supervisor->name.' has been assigned as your academic supervisor.',
            ['placement_id' => $placement->id],
        ));

        $supervisor->notify(new \App\Notifications\WorkspaceNotice(
            'Student assigned for supervision',
            'You have been assigned to '.$placement->student->name.' in '.$placement->student->course.'.',
            ['placement_id' => $placement->id],
        ));

        return back()->with('status', 'The academic supervisor assignment has been saved.');
    }
}
