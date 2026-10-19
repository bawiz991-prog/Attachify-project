<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyReportController extends Controller
{
    public function store(Request $request, Placement $placement): RedirectResponse
    {
        abort_unless($placement->student_id === $request->user()->id, 403);

        $validated = $request->validate([
            'report_date' => ['required', 'date', 'after_or_equal:'.CarbonImmutable::parse($placement->start_date)->toDateString(), 'before_or_equal:'.CarbonImmutable::parse($placement->end_date)->toDateString(), 'before_or_equal:today'],
            'activities' => ['required', 'string', 'min:10', 'max:5000'],
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
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return redirect()->route('dashboard')->with('status', 'Your daily report has been submitted.');
    }
}