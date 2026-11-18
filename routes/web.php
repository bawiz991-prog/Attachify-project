<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\CoordinatorController;
use App\Http\Controllers\DailyReportCommentController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\FinalReportController;
use App\Http\Controllers\FacultyPlacementController;
use App\Http\Controllers\FieldSupervisorInvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlacementAssessmentController;
use App\Http\Controllers\PlacementController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\StudentVerificationController;
use App\Http\Controllers\SupervisorAssessmentController;
use App\Http\Controllers\SupervisorMeetingController;
use App\Http\Controllers\SupervisorMessageController;
use App\Http\Controllers\SupportController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Models\Placement;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('attachify');
});

Route::get('/help', [SupportController::class, 'help'])->name('help');
Route::get('/contact', [SupportController::class, 'contact'])->name('contact');
Route::post('/contact', [SupportController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::get('/field-supervisor-invitations/{token}', [FieldSupervisorInvitationController::class, 'show'])->name('field-supervisor-invitations.show');
Route::post('/field-supervisor-invitations/{token}', [FieldSupervisorInvitationController::class, 'accept'])->name('field-supervisor-invitations.accept');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    EnsureAccountIsActive::class,
    EnsurePasswordChanged::class,
])->group(function () {
    Route::get('/account/password', [AccountPasswordController::class, 'edit'])->name('account.password.edit');
    Route::post('/account/password', [AccountPasswordController::class, 'update'])->name('account.password.update');
    Route::post('/account/profile-photo', [ProfilePhotoController::class, 'update'])->name('profile.photo.update');

    Route::get('/dashboard', function (Request $request) {
        if ($request->user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($request->user()->role === 'coordinator') {
            return redirect()->route('coordinator.dashboard');
        }

        if (in_array($request->user()->role, ['faculty_handler', 'faculty_dean'], true)) {
            return redirect()->route('faculty.dashboard');
        }

        if ($request->user()->role !== 'student') {
            $supervisions = ! $request->user()->isApproved()
                ? Placement::query()->whereRaw('1 = 0')
                : ($request->user()->role === 'field_supervisor'
                ? $request->user()->fieldSupervisions()->whereDate('end_date', '>=', today())
                : $request->user()->academicSupervisions());
            $placements = $supervisions
                ->with([
                    'student',
                    'meetings' => fn ($query) => $query->latest('starts_at'),
                    'supervisorMessages.sender',
                    'finalAssessments.supervisor',
                    'dailyReports' => fn ($query) => $query
                        ->whereIn('status', ['submitted', 'field_approved', 'academic_reviewed'])
                        ->latest('report_date')
                        ->limit(5)
                        ->with([
                            'comments.author',
                            'assessments' => fn ($assessments) => $request->user()->role === 'field_supervisor'
                                ? $assessments->where('supervisor_id', $request->user()->id)->latest('submitted_at')
                                : $assessments->latest('submitted_at'),
                        ]),
                    'studentFinalReports' => fn ($reports) => $reports->latest('submitted_at')->limit(1),
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
                'studentFinalReports' => fn ($reports) => $reports->latest('submitted_at')->limit(1),
            ])
            ->withCount('dailyReports')
            ->latest()
            ->first();

        return view('dashboard', [
            'placement' => $placement,
            'programs' => Program::query()->orderBy('name')->get(),
            'program' => Program::query()->where('name', $request->user()->course)->first(),
            'faculties' => config('faculties'),
            'notifications' => $request->user()->unreadNotifications()->latest()->limit(5)->get(),
        ]);
    })->name('dashboard');

    Route::middleware('administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('dashboard');
        Route::post('/users', [AdminController::class, 'createUser'])->name('users.store');
        Route::patch('/users/{user}/status', [AdminController::class, 'setUserActive'])->name('users.status');
        Route::put('/programs/{program}/faculty', [AdminController::class, 'updateProgramFaculty'])->name('programs.faculty');
        Route::post('/users/{user}/review', [AdminController::class, 'reviewUser'])->name('users.review');
        Route::post('/placements/{placement}/review', [AdminController::class, 'reviewPlacement'])->name('placements.review');
        Route::put('/placements/{placement}', [AdminController::class, 'updatePlacement'])->name('placements.update');
        Route::post('/placements/{placement}/academic-supervisor', [PlacementController::class, 'assignAcademicSupervisor'])->name('placements.academic-supervisor');
        Route::get('/placements/{placement}/documents/{kind}', [AdminController::class, 'document'])->name('placements.document');
        Route::post('/announcements', [AdminController::class, 'announce'])->name('announcements.store');
        Route::post('/support/{supportRequest}/reply', [AdminController::class, 'replyToSupport'])->name('support.reply');
    });

    Route::get('/coordinator', [CoordinatorController::class, 'index'])->name('coordinator.dashboard');
    Route::post('/coordinator/assessments/{assessment}/forward', [CoordinatorController::class, 'forward'])->name('coordinator.assessments.forward');
    Route::get('/faculty', [FacultyPlacementController::class, 'index'])->name('faculty.dashboard');
    Route::post('/faculty/students/{student}/review', [FacultyPlacementController::class, 'reviewStudent'])->name('faculty.students.review');
    Route::post('/faculty/placements/{placement}/field-supervisor-invitation', [FacultyPlacementController::class, 'inviteFieldSupervisor'])->name('faculty.placements.field-supervisor-invitation');
    Route::get('/faculty/placements/{placement}/documents/{kind}', [FacultyPlacementController::class, 'document'])->name('faculty.documents');
    Route::post('/faculty/placements/{placement}/verification-request', [FacultyPlacementController::class, 'requestStudentVerification'])->name('faculty.placements.verification-request');
    Route::put('/faculty/placements/{placement}', [FacultyPlacementController::class, 'updateDetails'])->name('faculty.placements.update');
    Route::post('/faculty/placements/{placement}/dean-review', [FacultyPlacementController::class, 'deanReview'])->name('faculty.placements.dean-review');
    Route::post('/placements/register', [FacultyPlacementController::class, 'store'])->name('placements.register');
    Route::post('/placements/{placement}/verification-response', [FacultyPlacementController::class, 'respondToVerification'])->name('placements.verification-response');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::put('/student/identity', [StudentVerificationController::class, 'update'])->name('student.identity.update');
    Route::post('/placements/{placement}/field-supervisor', [PlacementController::class, 'connectFieldSupervisor'])->name('placements.field-supervisor');
    Route::post('/placements/{placement}/confirm', [PlacementController::class, 'confirm'])->name('placements.confirm');
    Route::post('/placements/{placement}/documents', [PlacementController::class, 'uploadDocuments'])->name('placements.documents.store');
    Route::post('/placements/{placement}/reports/{dailyReport}/assessment', [SupervisorAssessmentController::class, 'store'])->name('reports.assessments.store');
    Route::post('/placements/{placement}/reports/{dailyReport}/comments', [DailyReportCommentController::class, 'store'])->name('reports.comments.store');
    Route::post('/placements/{placement}/reports', [DailyReportController::class, 'store'])->name('reports.store');
    Route::post('/placements/{placement}/meetings', [SupervisorMeetingController::class, 'store'])->name('placements.meetings.store');
    Route::post('/placements/{placement}/meetings/{meeting}/complete', [SupervisorMeetingController::class, 'complete'])->name('placements.meetings.complete');
    Route::post('/placements/{placement}/final-report-template', [FinalReportController::class, 'storeTemplate'])->name('placements.final-report-template.store');
    Route::get('/placements/{placement}/final-report-template', [FinalReportController::class, 'downloadTemplate'])->name('placements.final-report-template.download');
    Route::post('/placements/{placement}/final-report', [FinalReportController::class, 'submit'])->name('placements.final-report.store');
    Route::post('/final-reports/{report}/review', [FinalReportController::class, 'review'])->name('final-reports.review');
    Route::get('/final-reports/{report}/download', [FinalReportController::class, 'download'])->name('final-reports.download');
    Route::post('/placements/{placement}/messages', [SupervisorMessageController::class, 'store'])->name('placements.messages.store');
    Route::post('/placements/{placement}/final-assessments', [PlacementAssessmentController::class, 'store'])->name('placements.final-assessments.store');
    Route::get('/placements/{placement}/faculty-report', [PlacementAssessmentController::class, 'report'])->name('placements.faculty-report');
});
