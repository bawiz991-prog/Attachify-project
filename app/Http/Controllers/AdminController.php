<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'users' => User::query()->where('role', '!=', 'admin')->latest()->paginate(25, ['*'], 'users'),
            'placements' => Placement::query()
                ->with([
                    'student',
                    'fieldSupervisor',
                    'academicSupervisor',
                    'dailyReports.comments.author',
                    'dailyReports.assessments.supervisor',
                    'finalAssessments.supervisor',
                ])
                ->latest()
                ->paginate(15, ['*'], 'placements'),
            'supportRequests' => SupportRequest::query()->latest()->limit(20)->get(),
            'pendingUsers' => User::query()->where('verification_status', 'pending')->count(),
            'pendingPlacements' => Placement::query()->where('document_status', 'pending')->count(),
            'openSupportRequests' => SupportRequest::query()->where('status', 'open')->count(),
        ]);
    }

    public function reviewUser(Request $request, User $user): RedirectResponse
    {
        abort_if($user->role === 'admin', 403, 'Administrator accounts cannot be reviewed here.');

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        if ($validated['decision'] === 'approved' && $user->role === 'student') {
            abort_unless(
                $user->course && $user->registration_number && $user->student_number,
                422,
                'A student must provide a course, registration number, and student number before approval.'
            );
        }

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

    public function announce(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'audience' => ['required', 'in:all,students,supervisors'],
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:3', 'max:3000'],
        ]);

        $users = User::query()->where('verification_status', 'approved');
        if ($validated['audience'] === 'students') {
            $users->where('role', 'student');
        } elseif ($validated['audience'] === 'supervisors') {
            $users->whereIn('role', ['field_supervisor', 'academic_supervisor']);
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
