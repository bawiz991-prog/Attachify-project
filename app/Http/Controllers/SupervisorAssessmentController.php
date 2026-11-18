<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\Placement;
use App\Models\Program;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupervisorAssessmentController extends Controller
{
    public function store(Request $request, Placement $placement, DailyReport $dailyReport): RedirectResponse
    {
        $supervisor = $request->user();
        abort_unless($dailyReport->placement_id === $placement->id, 404);
        abort_unless($supervisor->isApproved() && $placement->document_status === 'approved', 403);

        [$assignedSupervisorId, $requiredStatus, $nextStatus] = match ($supervisor->role) {
            'field_supervisor' => [$placement->field_supervisor_id, 'submitted', 'field_approved'],
            'academic_supervisor' => [$placement->academic_supervisor_id, 'field_approved', 'academic_reviewed'],
            default => [null, null, null],
        };

        abort_unless($assignedSupervisorId === $supervisor->id, 403);
        abort_unless(
            $dailyReport->status === $requiredStatus,
            403,
            $supervisor->role === 'academic_supervisor'
                ? 'The field supervisor must approve this daily report first.'
                : 'This report is no longer waiting for field supervisor approval.'
        );

        $alreadyAssessed = $dailyReport->assessments()
            ->where('supervisor_id', $supervisor->id)
            ->exists();

        if ($alreadyAssessed) {
            return back()->withErrors(['assessment' => 'You have already assessed this report.'])->withInput();
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'strengths' => ['required', 'string', 'min:10', 'max:3000'],
            'areas_for_improvement' => ['nullable', 'string', 'max:3000'],
            'overall_feedback' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        DB::transaction(function () use ($dailyReport, $validated, $placement, $supervisor, $nextStatus) {
            $dailyReport->assessments()->create([
                ...$validated,
                'placement_id' => $placement->id,
                'supervisor_id' => $supervisor->id,
                'submitted_at' => now(),
            ]);
            $dailyReport->forceFill(['status' => $nextStatus])->save();
        });

        if ($supervisor->role === 'field_supervisor') {
            $placement->academicSupervisor?->notify(new WorkspaceNotice(
                'Daily report ready for academic review',
                $supervisor->name.' approved '.$placement->student->name.'’s daily report for '.$dailyReport->report_date->format('M j, Y').'.',
                ['placement_id' => $placement->id, 'daily_report_id' => $dailyReport->id],
            ));
        } else {
            $coordinator = Program::query()
                ->where('name', $placement->student->course)
                ->with('coordinator')
                ->first()?->coordinator;
            $coordinator?->notify(new WorkspaceNotice(
                'Daily report reviewed by supervisors',
                'The daily report for '.$placement->student->name.' is ready for course coordination.',
                ['placement_id' => $placement->id, 'daily_report_id' => $dailyReport->id],
            ));
        }

        return redirect()->route('dashboard')->with('status', $supervisor->role === 'field_supervisor'
            ? 'The daily report has been approved and sent to the academic supervisor.'
            : 'Your academic review has been submitted.');
    }
}
