<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Notifications\WorkspaceNotice;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyReportController extends Controller
{
    public function store(Request $request, Placement $placement): RedirectResponse
    {
        abort_unless($placement->student_id === $request->user()->id, 403);
        abort_unless($request->user()->isApproved() && $placement->document_status === 'approved', 403, 'Your account and placement documents must be approved before submitting daily reports.');

        $validated = $request->validate([
            'report_date' => ['required', 'date', 'after_or_equal:'.CarbonImmutable::parse($placement->start_date)->toDateString(), 'before_or_equal:'.CarbonImmutable::parse($placement->end_date)->toDateString(), 'before_or_equal:today'],
            'activities' => ['required', 'string', 'min:10', 'max:5000'],
            'challenges' => ['required', 'string', 'min:3', 'max:3000'],
            'skills_acquired' => ['required', 'string', 'min:3', 'max:3000'],
            'lessons_learned' => ['required', 'string', 'min:3', 'max:3000'],
        ]);

        $reportDate = CarbonImmutable::parse($validated['report_date']);
        $isScheduledWorkday = $placement->schedules()
            ->where('day_of_week', $reportDate->dayOfWeekIso)
            ->exists();

        if (! $isScheduledWorkday) {
            return back()->withErrors(['report_date' => 'Choose a day included in your registered work schedule.'])->withInput();
        }

        $alreadySubmitted = $placement->dailyReports()
            ->whereDate('report_date', $reportDate->toDateString())
            ->exists();

        if ($alreadySubmitted) {
            return back()->withErrors(['report_date' => 'A report has already been submitted for this day.'])->withInput();
        }

        $placement->dailyReports()->create([
            'report_date' => $reportDate,
            'activities' => $validated['activities'],
            'challenges' => $validated['challenges'],
            'skills_acquired' => $validated['skills_acquired'],
            'lessons_learned' => $validated['lessons_learned'],
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        foreach ([$placement->fieldSupervisor, $placement->academicSupervisor] as $supervisor) {
            $supervisor?->notify(new WorkspaceNotice(
                'New daily report',
                $request->user()->name.' submitted a daily report for '.$reportDate->format('M j, Y').'.',
                ['placement_id' => $placement->id],
            ));
        }

        return redirect()->route('dashboard')->with('status', 'Your daily report has been submitted.');
    }
}
