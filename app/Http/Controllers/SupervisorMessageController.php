<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupervisorMessageController extends Controller
{
    public function store(Request $request, Placement $placement): RedirectResponse
    {
        $sender = $request->user();

        $recipientId = match ($sender->role) {
            'academic_supervisor' => $placement->field_supervisor_id,
            'field_supervisor' => $placement->academic_supervisor_id,
            default => null,
        };

        $assignedId = $sender->role === 'academic_supervisor'
            ? $placement->academic_supervisor_id
            : $placement->field_supervisor_id;

        abort_unless($assignedId === $sender->id && $recipientId, 403);
        abort_unless($sender->isApproved() && $placement->document_status === 'approved', 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:3000'],
        ]);

        $message = $placement->supervisorMessages()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipientId,
            'body' => $validated['body'],
        ]);

        $message->recipient->notify(new WorkspaceNotice(
            'Message from '.$sender->name,
            $message->body,
            ['placement_id' => $placement->id],
        ));

        return back()->with('status', 'Your message was sent to the other assigned supervisor.');
    }
}
