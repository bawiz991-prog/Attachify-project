<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\Placement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupervisorAssessmentController extends Controller
{
    public function store(Request $request, Placement $placement, DailyReport $dailyReport): RedirectResponse
    {
        $supervisor = $request->user();
        abort_unless($dailyReport->placement_id === $placement->id, 404);
        abort_unless($dailyReport->status === 'submitted', 403);
        abort_unless($supervisor->isApproved() && $placement->document_status === 'approved', 403);

        $assignedSupervisorId = match ($supervisor->role) {
            'field_supervisor' => $placement->field_supervisor_id,
            'academic_supervisor' => $placement->academic_supervisor_id,
            default => null,
        };

        abort_unless($assignedSupervisorId === $supervisor->id, 403);

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

        $dailyReport->assessments()->create([
            ...$validated,
            'placement_id' => $placement->id,
            'supervisor_id' => $supervisor->id,
            'submitted_at' => now(),
        ]);

        return redirect()->route('dashboard')->with('status', 'Your assessment has been submitted.');
    }
}
