<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DailyReportCommentController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlacementAssessmentController;
use App\Http\Controllers\PlacementController;
use App\Http\Controllers\StudentVerificationController;
use App\Http\Controllers\SupervisorAssessmentController;
use App\Http\Controllers\SupervisorMeetingController;
use App\Http\Controllers\SupervisorMessageController;
use App\Http\Controllers\SupportController;
use App\Models\Placement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('attachify');
});

Route::get('/help', [SupportController::class, 'help'])->name('help');
Route::get('/contact', [SupportController::class, 'contact'])->name('contact');
Route::post('/contact', [SupportController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function (Request $request) {
        if ($request->user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($request->user()->role !== 'student') {
            $supervisions = ! $request->user()->isApproved()
                ? Placement::query()->whereRaw('1 = 0')
                : ($request->user()->role === 'field_supervisor'
                ? $request->user()->fieldSupervisions()
                : $request->user()->academicSupervisions());
            $placements = $supervisions
                ->with([
                    'student',
                    'meetings' => fn ($query) => $query->latest('starts_at'),
                    'supervisorMessages.sender',
                    'finalAssessments.supervisor',
                    'dailyReports' => fn ($query) => $query
                        ->where('status', 'submitted')
                        ->latest('report_date')
                        ->limit(5)
                        ->with([
                            'comments.author',
                            'assessments' => fn ($assessments) => $request->user()->role === 'field_supervisor'
                                ? $assessments->where('supervisor_id', $request->user()->id)->latest('submitted_at')
                                : $assessments->latest('submitted_at'),
                        ]),
                ])
                ->latest()
                ->get();

            return view('supervisor-dashboard', [
                'placements' => $placements,
                'notifications' => $request->user()->unreadNotifications()->latest()->limit(5)->get(),
            ]);
        }

        $placement = $request->user()->placements()
            ->with([
                'academicSupervisor',
                'fieldSupervisor',
                'schedules',
                'meetings' => fn ($query) => $query->latest('starts_at'),
                'dailyReports' => fn ($query) => $query->with([
                    'comments' => fn ($comments) => $comments->whereHas('author', fn ($author) => $author->where('role', 'field_supervisor')),
                    'comments.author',
                ])->latest('report_date')->limit(5),
                'finalAssessments' => fn ($assessments) => $assessments
                    ->where('supervisor_role', 'academic_supervisor')
                    ->whereNotNull('faculty_report_path')
                    ->select(['id', 'placement_id', 'supervisor_role', 'faculty_report_path', 'submitted_at']),
            ])
            ->withCount('dailyReports')
            ->latest()
            ->first();

        return view('dashboard', [
            'placement' => $placement,
            'notifications' => $request->user()->unreadNotifications()->latest()->limit(5)->get(),
        ]);
    })->name('dashboard');

    Route::middleware('administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('dashboard');
        Route::post('/users/{user}/review', [AdminController::class, 'reviewUser'])->name('users.review');
        Route::post('/placements/{placement}/review', [AdminController::class, 'reviewPlacement'])->name('placements.review');
        Route::get('/placements/{placement}/documents/{kind}', [AdminController::class, 'document'])->name('placements.document');
        Route::post('/announcements', [AdminController::class, 'announce'])->name('announcements.store');
        Route::post('/support/{supportRequest}/reply', [AdminController::class, 'replyToSupport'])->name('support.reply');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::put('/student/identity', [StudentVerificationController::class, 'update'])->name('student.identity.update');
    Route::get('/placements/create', [PlacementController::class, 'create'])->name('placements.create');
    Route::post('/placements', [PlacementController::class, 'store'])->name('placements.store');
    Route::post('/placements/{placement}/field-supervisor', [PlacementController::class, 'connectFieldSupervisor'])->name('placements.field-supervisor');
    Route::post('/placements/{placement}/academic-supervisor', [PlacementController::class, 'connectAcademicSupervisor'])->name('placements.academic-supervisor');
    Route::post('/placements/{placement}/reports/{dailyReport}/assessment', [SupervisorAssessmentController::class, 'store'])->name('reports.assessments.store');
    Route::post('/placements/{placement}/reports/{dailyReport}/comments', [DailyReportCommentController::class, 'store'])->name('reports.comments.store');
    Route::post('/placements/{placement}/reports', [DailyReportController::class, 'store'])->name('reports.store');
    Route::post('/placements/{placement}/meetings', [SupervisorMeetingController::class, 'store'])->name('placements.meetings.store');
    Route::post('/placements/{placement}/messages', [SupervisorMessageController::class, 'store'])->name('placements.messages.store');
    Route::post('/placements/{placement}/final-assessments', [PlacementAssessmentController::class, 'store'])->name('placements.final-assessments.store');
    Route::get('/placements/{placement}/faculty-report', [PlacementAssessmentController::class, 'report'])->name('placements.faculty-report');
});
