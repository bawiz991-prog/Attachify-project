<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Models\Program;
use App\Models\StudentFinalReport;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinalReportController extends Controller
{
    public function storeTemplate(Request $request, Placement $placement): RedirectResponse
    {
        abort_unless(
            $request->user()->role === 'academic_supervisor'
                && $placement->academic_supervisor_id === $request->user()->id,
            403,
        );
        abort_unless(
            $placement->meetings()->where('mode', 'google_meet')->where('status', 'completed')->exists(),
            422,
            'Complete an online Google Meet assessment before sending the final report template.'
        );

        $validated = $request->validate([
            'template' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);
        $path = $validated['template']->store('final-report-templates');
        if (! $path) {
            throw new \RuntimeException('The final report template could not be stored. Please try again.');
        }

        $previousPath = $placement->final_report_template_path;
        $placement->forceFill([
            'final_report_template_path' => $path,
            'report_template_sent_at' => now(),
        ])->save();
        if ($previousPath) {
            Storage::disk('local')->delete($previousPath);
        }

        $placement->student->notify(new WorkspaceNotice(
            'Final report template sent',
            'Your academic supervisor sent the final report template. It will be available from one week before your placement ends.',
            ['placement_id' => $placement->id],
        ));

        return back()->with('status', 'The final report template was sent to the student.');
    }

    public function downloadTemplate(Request $request, Placement $placement): StreamedResponse
    {
        abort_unless($placement->student_id === $request->user()->id, 403);
        abort_unless($this->templateIsAvailable($placement), 404, 'The template is available during the final week of the placement.');
        abort_unless(Storage::disk('local')->exists($placement->final_report_template_path), 404);

        return Storage::disk('local')->download($placement->final_report_template_path, 'final-report-template.pdf');
    }

    public function submit(Request $request, Placement $placement): RedirectResponse
    {
        abort_unless($placement->student_id === $request->user()->id, 403);
        abort_unless($this->templateIsAvailable($placement), 403, 'The final report can be submitted during the final week of the placement.');

        $latest = $placement->studentFinalReports()->latest('submitted_at')->first();
        abort_if(
            $latest && in_array($latest->status, ['pending', 'approved'], true),
            409,
            'Your latest final report is still being reviewed or has already been approved.'
        );

        $validated = $request->validate([
            'final_report' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);
        $path = $validated['final_report']->store('student-final-reports');
        if (! $path) {
            throw new \RuntimeException('Your final report could not be stored. Please try again.');
        }

        $report = $placement->studentFinalReports()->create([
            'student_id' => $request->user()->id,
            'report_path' => $path,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $placement->academicSupervisor?->notify(new WorkspaceNotice(
            'Student final report submitted',
            $request->user()->name.' submitted a final report for review.',
            ['placement_id' => $placement->id, 'final_report_id' => $report->id],
        ));

        return back()->with('status', 'Your final report has been submitted to your academic supervisor.');
    }

    public function review(Request $request, StudentFinalReport $report): RedirectResponse
    {
        $placement = $report->placement;
        abort_unless(
            $request->user()->role === 'academic_supervisor'
                && $placement->academic_supervisor_id === $request->user()->id,
            403,
        );
        abort_unless($report->status === 'pending', 409, 'This final report has already been reviewed.');

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,revision_requested'],
            'reviewer_notes' => ['required', 'string', 'min:3', 'max:3000'],
        ]);

        $report->forceFill([
            'status' => $validated['decision'],
            'reviewer_notes' => $validated['reviewer_notes'],
            'reviewer_id' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        $report->student->notify(new WorkspaceNotice(
            $validated['decision'] === 'approved' ? 'Final report approved' : 'Final report needs correction',
            $validated['decision'] === 'approved'
                ? 'Your academic supervisor approved your final report.'
                : 'Your academic supervisor requested corrections. Review the feedback and submit a revised PDF.',
            ['placement_id' => $placement->id, 'final_report_id' => $report->id],
        ));

        $coordinator = Program::query()
            ->where('name', $report->student->course)
            ->with('coordinator')
            ->first()?->coordinator;
        $coordinator?->notify(new WorkspaceNotice(
            'Final report review completed',
            'The academic supervisor marked '.$report->student->name.'’s final report as '.$validated['decision'].'.',
            ['placement_id' => $placement->id, 'final_report_id' => $report->id],
        ));

        return back()->with('status', 'The final report review was saved and the student was notified.');
    }

    public function download(Request $request, StudentFinalReport $report): StreamedResponse
    {
        $report->loadMissing('placement.student');
        $placement = $report->placement;
        abort_unless(
            $request->user()->role === 'admin'
                || $placement->student_id === $request->user()->id
                || $placement->academic_supervisor_id === $request->user()->id
                || ($request->user()->role === 'coordinator' && $request->user()->course === $placement->student->course),
            403,
        );
        abort_unless(Storage::disk('local')->exists($report->report_path), 404);

        return Storage::disk('local')->download($report->report_path, 'student-final-report.pdf');
    }

    private function templateIsAvailable(Placement $placement): bool
    {
        return $placement->final_report_template_path
            && $placement->report_template_sent_at
            && $placement->end_date
            && today()->greaterThanOrEqualTo($placement->end_date->copy()->subDays(7))
            && today()->lessThanOrEqualTo($placement->end_date);
    }
}
