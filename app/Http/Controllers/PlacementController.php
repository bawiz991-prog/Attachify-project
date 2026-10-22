<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlacementController extends Controller
{
    public function create(): View
    {
        abort_unless(auth()->user()->role === 'student', 403);
        abort_unless(auth()->user()->isApproved(), 403, 'Your account must be approved before you can set up a placement.');

        return view('placements.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'student', 403);
        abort_unless($request->user()->isApproved(), 403, 'Your account must be approved before you can set up a placement.');
        abort_if($request->user()->placements()->exists(), 403, 'A placement has already been created for your account.');

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:2000'],
            'acceptance_document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'placement_document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'workdays' => ['required', 'array', 'min:1'],
            'workdays.*' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $documentPath = $request->file('acceptance_document')->store('acceptance-letters');
        if (! $documentPath) {
            throw new \RuntimeException('The company acceptance letter could not be stored. Please try again.');
        }

        $placementDocumentPath = $request->file('placement_document')->store('placement-letters');
        if (! $placementDocumentPath) {
            throw new \RuntimeException('The faculty placement letter could not be stored. Please try again.');
        }

        DB::transaction(function () use ($request, $validated, $documentPath, $placementDocumentPath) {
            $placement = $request->user()->placements()->create([
                'company_name' => $validated['company_name'],
                'company_address' => $validated['company_address'] ?? null,
                'acceptance_document_path' => $documentPath,
                'placement_document_path' => $placementDocumentPath,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'status' => 'active',
                'document_status' => 'pending',
            ]);

            foreach (array_unique($validated['workdays']) as $dayOfWeek) {
                $placement->schedules()->create([
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $validated['start_time'],
                    'end_time' => $validated['end_time'],
                ]);
            }
        });

        return redirect()->route('dashboard')->with('status', 'Your placement details have been saved.');
    }

    public function connectFieldSupervisor(Request $request, Placement $placement): RedirectResponse
    {
        abort_unless($placement->student_id === $request->user()->id, 403);
        abort_unless($request->user()->isApproved(), 403, 'Your account must be approved before connecting a supervisor.');

        $validated = $request->validate([
            'field_supervisor_email' => ['required', 'email', 'max:255'],
        ]);

        $supervisor = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($validated['field_supervisor_email'])])
            ->where('role', 'field_supervisor')
            ->where('verification_status', 'approved')
            ->first();

        if (! $supervisor) {
            return back()->withErrors([
                'field_supervisor_email' => 'No field supervisor account uses this email. Ask your supervisor to register first.',
            ])->withInput();
        }

        $placement->fieldSupervisor()->associate($supervisor);
        $placement->save();

        return redirect()->route('dashboard')->with('status', 'Your field supervisor has been connected.');
    }

    public function connectAcademicSupervisor(Request $request, Placement $placement): RedirectResponse
    {
        abort_unless($placement->student_id === $request->user()->id, 403);
        abort_unless($request->user()->isApproved(), 403, 'Your account must be approved before connecting a supervisor.');

        $validated = $request->validate([
            'academic_supervisor_email' => ['required', 'email', 'max:255'],
        ]);

        $supervisor = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($validated['academic_supervisor_email'])])
            ->where('role', 'academic_supervisor')
            ->where('verification_status', 'approved')
            ->first();

        if (! $supervisor) {
            return back()->withErrors([
                'academic_supervisor_email' => 'No academic supervisor account uses this email. Ask your assigned supervisor to register first.',
            ])->withInput();
        }

        $placement->academicSupervisor()->associate($supervisor);
        $placement->save();

        return redirect()->route('dashboard')->with('status', 'Your academic supervisor has been connected.');
    }
}
