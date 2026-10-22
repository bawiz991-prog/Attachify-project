<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\Placement;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyReportCommentController extends Controller
{
    public function store(Request $request, Placement $placement, DailyReport $dailyReport): RedirectResponse
    {
        abort_unless($dailyReport->placement_id === $placement->id, 404);
        abort_unless($dailyReport->status === 'submitted', 403);
        abort_unless(
            $request->user()->role === 'field_supervisor'
                && $placement->field_supervisor_id === $request->user()->id,
            403,
        );
        abort_unless($request->user()->isApproved() && $placement->document_status === 'approved', 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:3000'],
        ]);

        $dailyReport->comments()->create([
            'author_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        $placement->student->notify(new WorkspaceNotice(
            'Field supervisor feedback',
            $request->user()->name.' commented on your daily log for '.$dailyReport->report_date->format('M j, Y').'.',
            ['placement_id' => $placement->id],
        ));

        return back()->with('status', 'Your feedback was shared with the student and academic supervisor.');
    }
}
