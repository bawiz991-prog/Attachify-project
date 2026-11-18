<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Models\Program;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(Request $request): View
    {
        $faculty = $request->query('faculty');
        abort_if($faculty && ! in_array($faculty, config('faculties'), true), 404);

        $placements = Placement::query()->with([
            'student',
            'fieldSupervisor',
            'academicSupervisor',
            'facultyHandler',
            'schedules',
            'dailyReports.comments.author',
            'dailyReports.assessments.supervisor',
            'finalAssessments.supervisor',
            'studentFinalReports.reviewer',
        ])->latest();

        if ($faculty) {
            $placements->where('faculty_name', $faculty);
        }

        $facultyCounts = Placement::query()
            ->selectRaw('faculty_name, count(*) as placement_count')
            ->whereIn('faculty_name', config('faculties'))
            ->groupBy('faculty_name')
            ->pluck('placement_count', 'faculty_name');

        return view('admin.dashboard', [
            'users' => User::query()->where('role', '!=', 'admin')->latest()->paginate(25, ['*'], 'users'),
            'studentsWithoutPlacement' => User::query()
                ->where('role', 'student')
                ->where('verification_status', 'approved')
                ->whereNotNull('email_verified_at')
                ->whereDoesntHave('placements')
                ->orderBy('name')
                ->get(),
            'programs' => Program::query()->with('coordinator')->orderBy('name')->get(),
            'academicSupervisors' => User::query()
                ->where('role', 'academic_supervisor')
                ->where('verification_status', 'approved')
                ->where('is_active', true)
                ->whereNotNull('email_verified_at')
                ->orderBy('name')
                ->get(),
            'placements' => $placements->paginate(15, ['*'], 'placements')->withQueryString(),
            'faculties' => config('faculties'),
            'facultyCounts' => $facultyCounts,
            'selectedFaculty' => $faculty,
            'supportRequests' => SupportRequest::query()->latest()->limit(20)->get(),
            'pendingUsers' => User::query()->where('role', '!=', 'student')->where('verification_status', 'pending')->count(),
            'pendingPlacements' => Placement::query()->where('document_status', 'pending')->count(),
            'openSupportRequests' => SupportRequest::query()->where('status', 'open')->count(),
        ]);
    }

    public function createUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['student', 'field_supervisor', 'academic_supervisor', 'coordinator', 'faculty_handler', 'faculty_dean'])],
            'course' => ['required_if:role,academic_supervisor,coordinator', 'nullable', 'string', 'max:255'],
            'faculty' => ['required_if:role,faculty_handler,faculty_dean', 'nullable', Rule::in(config('faculties'))],
            'registration_number' => ['required_if:role,student', 'nullable', 'string', 'max:100', 'unique:users,registration_number'],
            'student_number' => ['required_if:role,student', 'nullable', 'string', 'max:100', 'unique:users,student_number'],
        ]);

        if ($validated['role'] === 'student'
            && ! preg_match('/@mubs\.ac\.ug$/i', $validated['email'])) {
            return back()
                ->withErrors(['email' => 'Student accounts must use a valid @mubs.ac.ug email address.'])
                ->withInput();
        }

        $program = null;
        if (! empty($validated['course']) && $validated['role'] !== 'student') {
            $program = Program::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['course'])])
                ->first();

            if (! $program) {
                return back()
                    ->withErrors(['course' => 'Choose a program from the official SIMS program list.'])
                    ->withInput();
            }
        }

        if ($validated['role'] === 'coordinator' && $program->coordinator_id) {
            return back()
                ->withErrors(['course' => 'This program already has a coordinator assigned.'])
                ->withInput();
        }

        $user = DB::transaction(function () use ($request, $validated, $program) {
            $user = new User;
            $user->forceFill([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'role' => $validated['role'],
                'faculty' => $validated['role'] === 'student' ? null : ($validated['faculty'] ?? null),
                'course' => $validated['role'] === 'student' ? null : $program?->name,
                'registration_number' => $validated['registration_number'] ?? null,
                'student_number' => $validated['student_number'] ?? null,
                'password' => 'password',
                'must_change_password' => true,
                'verification_status' => $validated['role'] === 'student' ? 'pending' : 'approved',
                'verified_by_id' => $validated['role'] === 'student' ? null : $request->user()->id,
                'verified_at' => $validated['role'] === 'student' ? null : now(),
            ])->save();

            if ($validated['role'] === 'coordinator') {
                $program->coordinator()->associate($user);
                $program->save();
            }

            return $user;
        });

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'The account was created. An activation link was sent to '.$user->email.'.');
    }

    public function setUserActive(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role !== 'admin' && $user->id !== $request->user()->id, 403);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = (bool) $validated['is_active'];
        $user->forceFill(['is_active' => $isActive])->save();

        return back()->with('status', $isActive
            ? 'The account has been reactivated.'
            : 'The account has been deactivated. Its records and history have been kept.');
    }

    public function updateProgramFaculty(Request $request, Program $program): RedirectResponse
    {
        $validated = $request->validate([
            'faculty' => ['nullable', Rule::in(config('faculties'))],
        ]);

        $program->update($validated);

        return back()->with('status', 'The faculty mapping for '.$program->name.' was saved.');
    }

    public function reviewUser(Request $request, User $user): RedirectResponse
    {
        abort_if(in_array($user->role, ['admin', 'student'], true), 403, 'Student details are reviewed by the student’s faculty.');

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $user->forceFill([
            'verification_status' => $validated['decision'],
            'verified_by_id' => $request->user()->id,
            'verified_at' => now(),
            'verification_notes' => $validated['notes'],
        ])->save();

        $user->notify(new WorkspaceNotice(
            $validated['decision'] === 'approved' ? 'Account approved' : 'Account needs attention',
            $validated['decision'] === 'approved'
                ? 'Your account details were verified. You can now use the placement workspace.'
                : 'Your account was not approved. Review the administrator’s note and contact support.',
        ));

        return back()->with('status', 'The account review has been saved and the user notified.');
    }

    public function reviewPlacement(Request $request, Placement $placement): RedirectResponse
    {
        abort_if($placement->faculty_review_status !== null, 403, 'The faculty dean owns the final decision for faculty placements.');

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
            'documents_match' => ['required_if:decision,approved', 'accepted'],
        ]);

        if ($validated['decision'] === 'approved') {
            abort_unless(
                $placement->placement_document_path && $placement->acceptance_document_path,
                422,
                'Both PDF letters must be uploaded before approval.'
            );
            abort_unless($placement->student->isApproved(), 422, 'Approve the student account before approving the placement.');
            abort_unless($placement->student_confirmed_at, 422, 'The student must confirm the internship place before final administrator approval.');
            abort_unless($placement->start_date && $placement->end_date, 422, 'The placement period must be recorded before approval.');
        }

        $placement->forceFill([
            'document_status' => $validated['decision'],
            'document_reviewed_by_id' => $request->user()->id,
            'document_reviewed_at' => now(),
            'document_review_notes' => $validated['notes'],
        ])->save();

        $placement->student->notify(new WorkspaceNotice(
            $validated['decision'] === 'approved' ? 'Placement approved' : 'Placement needs attention',
            $validated['decision'] === 'approved'
                ? 'Your placement letters were approved. Daily reports are now enabled.'
                : 'Your placement letters were not approved. Review the administrator’s note and contact support.',
            ['placement_id' => $placement->id],
        ));

        return back()->with('status', 'The placement document review has been saved and the student notified.');
    }

    public function updatePlacement(Request $request, Placement $placement): RedirectResponse
    {
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

        $requestedSchedule = collect(array_unique($validated['workdays']))
            ->map(fn ($day) => [(int) $day, substr($validated['start_time'], 0, 5), substr($validated['end_time'], 0, 5)])
            ->sortBy(fn ($schedule) => $schedule[0])
            ->values()
            ->all();
        $currentSchedule = $placement->schedules()
            ->orderBy('day_of_week')
            ->get()
            ->map(fn ($schedule) => [(int) $schedule->day_of_week, substr($schedule->start_time, 0, 5), substr($schedule->end_time, 0, 5)])
            ->all();
        $changed = $placement->company_name !== $validated['company_name']
            || $placement->company_address !== ($validated['company_address'] ?? null)
            || $placement->getRawOriginal('start_date') !== $validated['start_date']
            || $placement->getRawOriginal('end_date') !== $validated['end_date']
            || $currentSchedule !== $requestedSchedule;

        DB::transaction(function () use ($placement, $validated, $changed) {
            $placement->forceFill([
                'company_name' => $validated['company_name'],
                'company_address' => $validated['company_address'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]);

            if ($placement->faculty_review_status !== null && $changed) {
                $placement->forceFill([
                    'faculty_review_status' => 'submitted',
                    'document_status' => 'pending',
                    'student_verification_response' => null,
                    'student_confirmed_at' => null,
                    'document_reviewed_by_id' => null,
                    'document_reviewed_at' => null,
                    'document_review_notes' => null,
                ]);
            }

            $placement->save();
            $placement->schedules()->delete();

            foreach (array_unique($validated['workdays']) as $dayOfWeek) {
                $placement->schedules()->create([
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $validated['start_time'],
                    'end_time' => $validated['end_time'],
                ]);
            }
        });

        if ($changed && $placement->faculty_review_status !== null) {
            $placement->student->notify(new WorkspaceNotice(
                'Your internship details were updated by the administrator',
                'Your faculty has been asked to review the updated details and request your confirmation again.',
                ['placement_id' => $placement->id],
            ));

            User::query()
                ->where('faculty', $placement->faculty_name)
                ->whereIn('role', ['faculty_handler', 'faculty_dean'])
                ->where('is_active', true)
                ->get()
                ->each(fn (User $staff) => $staff->notify(new WorkspaceNotice(
                    'Administrator updated placement details',
                    'Review the revised placement details and ask the student to confirm them again.',
                    ['placement_id' => $placement->id],
                )));
        }

        return back()->with('status', 'The placement details were updated.');
    }

    public function announce(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'audience' => ['required', 'in:all,students,supervisors,faculty'],
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:3', 'max:3000'],
        ]);

        $users = User::query()->where('verification_status', 'approved');
        if ($validated['audience'] === 'students') {
            $users->where('role', 'student');
        } elseif ($validated['audience'] === 'supervisors') {
            $users->whereIn('role', ['field_supervisor', 'academic_supervisor']);
        } elseif ($validated['audience'] === 'faculty') {
            $users->whereIn('role', ['faculty_handler', 'faculty_dean']);
        } else {
            $users->where('role', '!=', 'admin');
        }

        $notice = new WorkspaceNotice($validated['title'], $validated['message']);
        $users->each(fn (User $user) => $user->notify($notice));

        return back()->with('status', 'The announcement was sent to '.$users->count().' accounts.');
    }

    public function document(Placement $placement, string $kind)
    {
        abort_unless(in_array($kind, ['acceptance', 'placement'], true), 404);

        $path = $kind === 'acceptance'
            ? $placement->acceptance_document_path
            : $placement->placement_document_path;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    public function replyToSupport(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $validated = $request->validate([
            'admin_reply' => ['required', 'string', 'min:3', 'max:3000'],
            'status' => ['required', 'in:open,resolved'],
        ]);

        $supportRequest->update($validated);

        if ($supportRequest->user) {
            $supportRequest->user->notify(new WorkspaceNotice(
                'Support replied',
                $validated['admin_reply'],
            ));
        }

        return back()->with('status', 'The support request was updated.');
    }
}
