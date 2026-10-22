<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlacementAssessmentController extends Controller
{
    public function store(Request $request, Placement $placement): RedirectResponse
    {
        $user = $request->user();
        $assigned = match ($user->role) {
            'field_supervisor' => $placement->field_supervisor_id === $user->id,
            'academic_supervisor' => $placement->academic_supervisor_id === $user->id,
            default => false,
        };
        abort_unless($assigned, 403);
        abort_unless($user->isApproved() && $placement->document_status === 'approved', 403);
        if ($user->role === 'field_supervisor') {
            abort_unless(
                $placement->start_date
                    && $placement->end_date
                    && today()->greaterThanOrEqualTo($placement->start_date)
                    && today()->lessThanOrEqualTo($placement->end_date),
                403,
                'Field supervisor final assessments are available during the placement period only.'
            );
        }
        abort_if($placement->finalAssessments()->where('supervisor_id', $user->id)->exists(), 409, 'A final assessment has already been submitted.');

        $rules = [
            'overall_score' => ['required', 'numeric', 'between:0,100'],
            'feedback' => ['required', 'string', 'min:10', 'max:5000'],
        ];

        if ($user->role === 'academic_supervisor') {
            $rules += [
                'physical_score' => ['required', 'numeric', 'between:0,100'],
                'teams_score' => ['required', 'numeric', 'between:0,100'],
                'faculty_report' => ['required', 'file', 'mimes:pdf', 'max:10240'],
                'assessment_confirmed' => ['accepted'],
            ];
        }

        $validated = $request->validate($rules);
        $reportPath = null;
        if ($user->role === 'academic_supervisor') {
            $reportPath = $request->file('faculty_report')->store('faculty-reports');
            if (! $reportPath) {
                throw new \RuntimeException('The faculty report could not be stored. Please try again.');
            }
        }

        $assessment = $placement->finalAssessments()->create([
            'supervisor_id' => $user->id,
            'supervisor_role' => $user->role,
            'overall_score' => $validated['overall_score'],
            'physical_score' => $validated['physical_score'] ?? null,
            'teams_score' => $validated['teams_score'] ?? null,
            'feedback' => $validated['feedback'],
            'faculty_report_path' => $reportPath,
            'submitted_at' => now(),
        ]);

        if ($user->role === 'academic_supervisor') {
            $placement->student->notify(new WorkspaceNotice(
                'Faculty assessment report available',
                'Your academic supervisor uploaded your final faculty assessment report.',
                ['placement_id' => $placement->id],
            ));
        } elseif ($placement->academicSupervisor) {
            $placement->academicSupervisor->notify(new WorkspaceNotice(
                'Field supervisor final assessment',
                $user->name.' submitted a final assessment for '.$placement->student->name.'.',
                ['placement_id' => $placement->id],
            ));
        }

        return back()->with('status', 'The final assessment has been submitted.');
    }

    public function report(Request $request, Placement $placement): StreamedResponse
    {
        abort_unless($request->user()->role === 'admin'
            || $placement->student_id === $request->user()->id
            || $placement->academic_supervisor_id === $request->user()->id
            || $placement->field_supervisor_id === $request->user()->id, 403);

        $assessment = $placement->finalAssessments()
            ->where('supervisor_role', 'academic_supervisor')
            ->whereNotNull('faculty_report_path')
            ->latest('submitted_at')
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($assessment->faculty_report_path), 404);

        return Storage::disk('local')->download($assessment->faculty_report_path);
    }
}
