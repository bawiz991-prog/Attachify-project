<?php

use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\PlacementController;
use App\Http\Controllers\SupervisorAssessmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('attachify');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
        if ($request->user()->role !== 'student') {
            $supervisions = $request->user()->role === 'field_supervisor'
                ? $request->user()->fieldSupervisions()
                : $request->user()->academicSupervisions();
            $placements = $supervisions
                ->with([
                    'student',
                    'dailyReports' => fn ($query) => $query
                        ->where('status', 'submitted')
                        ->latest('report_date')
                        ->limit(5)
                        ->with(['assessments' => fn ($assessments) => $assessments->where('supervisor_id', $request->user()->id)->latest('submitted_at')]),
                ])
                ->latest()
                ->get();

            return view('supervisor-dashboard', compact('placements'));
        }

        $placement = $request->user()->placements()
            ->with([
                'academicSupervisor',
                'fieldSupervisor',
                'schedules',
                'dailyReports' => fn ($query) => $query->latest('report_date')->limit(5),
            ])
            ->withCount('dailyReports')
            ->latest()
            ->first();

        return view('dashboard', compact('placement'));
    })->name('dashboard');

    Route::get('/placements/create', [PlacementController::class, 'create'])->name('placements.create');
    Route::post('/placements', [PlacementController::class, 'store'])->name('placements.store');
    Route::post('/placements/{placement}/field-supervisor', [PlacementController::class, 'connectFieldSupervisor'])->name('placements.field-supervisor');
    Route::post('/placements/{placement}/academic-supervisor', [PlacementController::class, 'connectAcademicSupervisor'])->name('placements.academic-supervisor');
    Route::post('/placements/{placement}/reports/{dailyReport}/assessment', [SupervisorAssessmentController::class, 'store'])->name('reports.assessments.store');
    Route::post('/placements/{placement}/reports', [DailyReportController::class, 'store'])->name('reports.store');
});
