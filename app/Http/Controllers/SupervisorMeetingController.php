<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupervisorMeetingController extends Controller
{
    public function store(Request $request, Placement $placement): RedirectResponse
    {
        abort_unless(
            $request->user()->role === 'academic_supervisor'
                && $placement->academic_supervisor_id === $request->user()->id,
            403,
        );
        abort_unless($request->user()->isApproved() && $placement->document_status === 'approved', 403);

        $validated = $request->validate([
            'mode' => ['required', 'in:teams,in_person'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'teams_url' => ['required_if:mode,teams', 'nullable', 'url', 'max:2000'],
            'location' => ['required_if:mode,in_person', 'nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $meeting = $placement->meetings()->create([
            ...$validated,
            'supervisor_id' => $placement->field_supervisor_id,
            'scheduled_by_id' => $request->user()->id,
            'status' => 'scheduled',
        ]);

        $message = $meeting->mode === 'teams'
            ? 'A Microsoft Teams meeting'
            : 'An in-person meeting';

        foreach ([$placement->student, $placement->fieldSupervisor] as $participant) {
            $participant?->notify(new WorkspaceNotice(
                'Supervision meeting scheduled',
                $message.' is scheduled for '.$meeting->starts_at->format('M j, Y g:i A').'.',
                ['placement_id' => $placement->id],
            ));
        }

        return back()->with('status', 'The meeting was scheduled with the student and field supervisor.');
    }
}
