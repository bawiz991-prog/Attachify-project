<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupervisorMeetingController extends Controller
{
    public function complete(Request $request, Placement $placement, \App\Models\SupervisorMeeting $meeting): RedirectResponse
    {
        abort_unless($meeting->placement_id === $placement->id, 404);
        abort_unless(
            $request->user()->role === 'academic_supervisor'
                && $placement->academic_supervisor_id === $request->user()->id,
            403,
        );
        abort_unless($meeting->mode === 'google_meet' && $meeting->starts_at->isPast(), 422);
        abort_unless($meeting->status === 'scheduled', 409, 'This meeting has already been completed.');

        $meeting->forceFill(['status' => 'completed'])->save();

        return back()->with('status', 'The Google Meet supervision was marked complete.');
    }

    public function store(Request $request, Placement $placement): RedirectResponse
    {
        abort_unless(
            $request->user()->role === 'academic_supervisor'
                && $placement->academic_supervisor_id === $request->user()->id,
            403,
        );
        abort_unless($request->user()->isApproved() && $placement->document_status === 'approved', 403);

        $validated = $request->validate([
            'mode' => ['required', 'in:google_meet,in_person'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'teams_url' => [
                'required_if:mode,google_meet',
                'nullable',
                'url',
                'max:2000',
                'regex:/^https:\/\/meet\.google\.com\//i',
            ],
            'location' => ['required_if:mode,in_person', 'nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $meeting = $placement->meetings()->create([
            ...$validated,
            'supervisor_id' => $placement->field_supervisor_id,
            'scheduled_by_id' => $request->user()->id,
            'status' => 'scheduled',
        ]);

        $message = $meeting->mode === 'google_meet'
            ? 'A Google Meet assessment'
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
