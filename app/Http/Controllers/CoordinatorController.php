<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Models\PlacementAssessment;
use App\Models\User;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoordinatorController extends Controller
{
    public function index(Request $request): View
    {
        $coordinator = $request->user();
        abort_unless($coordinator->role === 'coordinator' && $coordinator->course, 403);

        return view('coordinator.dashboard', [
            'placements' => Placement::query()
                ->whereHas('student', fn ($query) => $query->where('course', $coordinator->course))
                ->with([
                    'student',
                    'academicSupervisor',
                    'fieldSupervisor',
                    'dailyReports' => fn ($query) => $query
                        ->latest('report_date')
                        ->limit(10)
                        ->with('assessments.supervisor'),
                    'finalAssessments.supervisor',
                ])
                ->latest()
                ->paginate(25),
            'notifications' => $coordinator->unreadNotifications()->latest()->limit(5)->get(),
        ]);
    }

    public function forward(Request $request, PlacementAssessment $assessment): RedirectResponse
    {
        $coordinator = $request->user();
        abort_unless($coordinator->role === 'coordinator' && $coordinator->course, 403);

        $assessment->loadMissing('placement.student');
        abort_unless(
            $assessment->supervisor_role === 'academic_supervisor'
                && $assessment->placement->student->course === $coordinator->course,
            404,
        );
        abort_unless(
            $assessment->placement->finalAssessments()
                ->where('supervisor_role', 'field_supervisor')
                ->where('supervisor_id', $assessment->placement->field_supervisor_id)
                ->exists(),
            422,
            'The field supervisor final assessment must be received before forwarding this student’s forms.'
        );
        abort_if($assessment->coordinator_forwarded_at, 409, 'This report has already been forwarded.');

        $assessment->forceFill(['coordinator_forwarded_at' => now()])->save();

        User::query()->where('role', 'admin')->each(fn (User $facultyOffice) => $facultyOffice->notify(new WorkspaceNotice(
            'Assessment forwarded to faculty',
            $coordinator->name.' forwarded the final assessment for '.$assessment->placement->student->name.'.',
            ['placement_id' => $assessment->placement_id, 'assessment_id' => $assessment->id],
        )));

        return back()->with('status', 'The student assessment has been submitted to the faculty office.');
    }
}
