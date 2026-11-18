<?php

namespace App\Http\Controllers;

use App\Models\FieldSupervisorInvitation;
use App\Models\Placement;
use App\Models\Program;
use App\Models\User;
use App\Notifications\FieldSupervisorInvitationNotice;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FacultyPlacementController extends Controller
{
    public function index(Request $request): View
    {
        $staff = $request->user();
        abort_unless(in_array($staff->role, ['faculty_handler', 'faculty_dean'], true) && $staff->faculty, 403);

        return view('faculty.dashboard', [
            'pendingStudents' => User::query()
                ->where('role', 'student')
                ->where('faculty', $staff->faculty)
                ->where('verification_status', 'pending')
                ->orderBy('name')
                ->get(),
            'placements' => Placement::query()
                ->where('faculty_name', $staff->faculty)
                ->with(['student', 'schedules', 'facultyHandler'])
                ->latest()
                ->paginate(25),
            'faculty' => $staff->faculty,
        ]);
    }

    public function reviewStudent(Request $request, User $student): RedirectResponse
    {
        $staff = $request->user();
        abort_unless(in_array($staff->role, ['faculty_handler', 'faculty_dean'], true), 403);
        abort_unless($staff->faculty && $student->role === 'student' && $student->faculty === $staff->faculty, 404);
        abort_unless($student->verification_status === 'pending', 409, 'This student account has already been reviewed.');

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        if ($validated['decision'] === 'approved') {
            abort_unless(
                Program::query()->where('name', $student->course)->exists()
                    && in_array($student->faculty, config('faculties'), true)
                    && $student->registration_number
                    && $student->student_number
                    && preg_match('/@mubs\.ac\.ug$/i', $student->email) === 1,
                422,
                'The student must provide a valid MUBS email, course, faculty, registration number, and student number.'
            );
        }

        $student->forceFill([
            'verification_status' => $validated['decision'],
            'verified_by_id' => $staff->id,
            'verified_at' => now(),
            'verification_notes' => $validated['notes'],
        ])->save();

        $student->notify(new WorkspaceNotice(
            $validated['decision'] === 'approved' ? 'Student details approved by your faculty' : 'Student details need attention',
            $validated['decision'] === 'approved'
                ? 'Your faculty verified your student details. You can now register your internship placement.'
                : 'Your faculty could not verify your details. Review the faculty note and update your student details.',
        ));

        return back()->with('status', 'The student details review was saved and the student notified.');
    }

    public function inviteFieldSupervisor(Request $request, Placement $placement): RedirectResponse
    {
        $staff = $request->user();
        $this->authorizeFaculty($staff, $placement, ['faculty_handler', 'faculty_dean']);
        abort_unless($placement->field_supervisor_email && ! $placement->field_supervisor_id, 409);

        $token = Str::random(64);
        FieldSupervisorInvitation::query()
            ->where('placement_id', $placement->id)
            ->whereNull('accepted_at')
            ->delete();

        FieldSupervisorInvitation::query()->create([
            'placement_id' => $placement->id,
            'email' => $placement->field_supervisor_email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
            'invited_by_id' => $staff->id,
        ]);

        Notification::route('mail', $placement->field_supervisor_email)->notify(
            new FieldSupervisorInvitationNotice(
                route('field-supervisor-invitations.show', $token),
                $placement->student->name,
                $placement->company_name,
            )
        );

        return back()->with('status', 'A seven-day invitation was sent to '.$placement->field_supervisor_email.'.');
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $request->user();
        abort_unless($student->role === 'student' && $student->isApproved(), 403);
        abort_if($student->placements()->exists(), 409, 'You have already registered an internship placement.');

        $programExists = Program::query()
            ->where('name', $student->course)
            ->exists();

        if (! $programExists) {
            throw ValidationException::withMessages([
                'course' => 'Your course is not in the active program list. Contact your administrator.',
            ]);
        }

        if (! in_array($student->faculty, config('faculties'), true)) {
            throw ValidationException::withMessages([
                'faculty' => 'Choose your faculty in your student details before registering a placement.',
            ]);
        }

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'workdays' => ['required', 'array', 'min:1'],
            'workdays.*' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'acceptance_document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'placement_document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $acceptancePath = $request->file('acceptance_document')->store('acceptance-letters');
        $placementPath = $request->file('placement_document')->store('placement-letters');

        $placement = DB::transaction(function () use ($student, $validated, $acceptancePath, $placementPath) {
            $placement = $student->placements()->create([
                'faculty_name' => $student->faculty,
                'faculty_review_status' => 'submitted',
                'company_name' => $validated['company_name'],
                'company_address' => $validated['company_address'] ?? null,
                'acceptance_document_path' => $acceptancePath,
                'placement_document_path' => $placementPath,
                'document_status' => 'pending',
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'status' => 'active',
            ]);

            foreach (array_unique($validated['workdays']) as $dayOfWeek) {
                $placement->schedules()->create([
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $validated['start_time'],
                    'end_time' => $validated['end_time'],
                ]);
            }

            return $placement;
        });

        $this->notifyFaculty(
            $student->faculty,
            'New student internship submission',
            $student->name.' submitted internship details for faculty review.',
            $placement,
        );

        return back()->with('status', 'Your placement has been submitted to '.$student->faculty.' for confirmation.');
    }

    public function requestStudentVerification(Request $request, Placement $placement): RedirectResponse
    {
        $staff = $request->user();
        $this->authorizeFaculty($staff, $placement, ['faculty_handler']);

        $validated = $request->validate([
            'request_message' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        abort_unless(in_array($placement->faculty_review_status, ['submitted', 'correction_requested'], true), 409);

        $placement->forceFill([
            'faculty_handler_id' => $staff->id,
            'faculty_review_status' => 'verification_requested',
            'faculty_verification_request' => $validated['request_message'],
        ])->save();

        $placement->student->notify(new WorkspaceNotice(
            'Faculty asks you to verify your internship details',
            $validated['request_message'],
            ['placement_id' => $placement->id],
        ));

        return back()->with('status', 'The student was asked to verify the internship details.');
    }

    public function updateDetails(Request $request, Placement $placement): RedirectResponse
    {
        $staff = $request->user();
        $this->authorizeFaculty($staff, $placement, ['faculty_handler']);
        abort_unless($placement->faculty_review_status === 'correction_requested', 409);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'workdays' => ['required', 'array', 'min:1'],
            'workdays.*' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        DB::transaction(function () use ($placement, $validated) {
            $placement->forceFill([
                'student_verification_response' => null,
                'student_confirmed_at' => null,
            ])->save();
            $placement->update([
                'company_name' => $validated['company_name'],
                'company_address' => $validated['company_address'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]);
            $placement->schedules()->delete();

            foreach (array_unique($validated['workdays']) as $dayOfWeek) {
                $placement->schedules()->create([
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $validated['start_time'],
                    'end_time' => $validated['end_time'],
                ]);
            }
        });

        return back()->with('status', 'The student’s requested corrections were applied. Request verification again when ready.');
    }

    public function respondToVerification(Request $request, Placement $placement): RedirectResponse
    {
        abort_unless($placement->student_id === $request->user()->id, 403);
        abort_unless($placement->faculty_review_status === 'verification_requested', 409);

        $validated = $request->validate([
            'decision' => ['required', 'in:confirmed,correction_requested'],
            'response' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $confirmed = $validated['decision'] === 'confirmed';
        $placement->forceFill([
            'faculty_review_status' => $confirmed ? 'student_confirmed' : 'correction_requested',
            'student_verification_response' => $validated['response'],
            'student_confirmed_at' => $confirmed ? now() : null,
        ])->save();

        $this->notifyFaculty(
            $placement->faculty_name,
            $confirmed ? 'Student verified internship details' : 'Student requested internship corrections',
            $request->user()->name.': '.$validated['response'],
            $placement,
        );

        return back()->with('status', $confirmed
            ? 'Your confirmation was sent to the faculty dean for final review.'
            : 'Your correction request was sent to the faculty.');
    }

    public function deanReview(Request $request, Placement $placement): RedirectResponse
    {
        $dean = $request->user();
        $this->authorizeFaculty($dean, $placement, ['faculty_dean']);
        abort_unless($placement->faculty_review_status === 'student_confirmed', 409);

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        if ($validated['decision'] === 'approved') {
            abort_unless(
                $placement->acceptance_document_path
                    && $placement->placement_document_path
                    && $placement->start_date
                    && $placement->end_date
                    && $placement->student->isApproved(),
                422,
                'The student, internship period, and both supporting letters must be present before approval.'
            );
        }

        $placement->forceFill([
            'faculty_review_status' => $validated['decision'],
            'document_status' => $validated['decision'],
            'document_reviewed_by_id' => $dean->id,
            'document_reviewed_at' => now(),
            'document_review_notes' => $validated['notes'],
        ])->save();

        $placement->student->notify(new WorkspaceNotice(
            $validated['decision'] === 'approved' ? 'Internship approved by your faculty dean' : 'Internship was not approved',
            $validated['notes'],
            ['placement_id' => $placement->id],
        ));

        return back()->with('status', 'The dean’s final placement decision was saved.');
    }

    public function document(Request $request, Placement $placement, string $kind)
    {
        $this->authorizeFaculty($request->user(), $placement, ['faculty_handler', 'faculty_dean']);
        abort_unless(in_array($kind, ['acceptance', 'placement'], true), 404);

        $path = $kind === 'acceptance'
            ? $placement->acceptance_document_path
            : $placement->placement_document_path;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->download(Storage::disk('local')->path($path));
    }

    private function authorizeFaculty(User $user, Placement $placement, array $roles): void
    {
        abort_unless(in_array($user->role, $roles, true), 403);
        abort_unless($user->faculty && $placement->faculty_name === $user->faculty, 404);
    }

    private function notifyFaculty(string $faculty, string $title, string $message, Placement $placement): void
    {
        User::query()
            ->where('faculty', $faculty)
            ->whereIn('role', ['faculty_handler', 'faculty_dean'])
            ->where('is_active', true)
            ->get()
            ->each(fn (User $staff) => $staff->notify(new WorkspaceNotice(
                $title,
                $message,
                ['placement_id' => $placement->id],
            )));
    }
}