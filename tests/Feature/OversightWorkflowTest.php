<?php

use App\Models\Placement;
use App\Models\FieldSupervisorInvitation;
use App\Models\Program;
use App\Models\User;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;

test('faculty reviews student accounts and administrators review legacy placement documents', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create([
        'email' => 'review.student@mubs.ac.ug',
        'role' => 'student',
        'verification_status' => 'pending',
        'course' => 'BACHELOR OF COMMERCE',
        'faculty' => 'Faculty of Commerce',
        'registration_number' => 'REG-100',
        'student_number' => 'STU-100',
    ]);
    $placement = Placement::create([
        'student_id' => $student->id,
        'company_name' => 'Northstar Engineering',
        'acceptance_document_path' => 'acceptance-letters/acceptance.pdf',
        'placement_document_path' => 'placement-letters/placement.pdf',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
        'document_status' => 'pending',
        'student_confirmed_at' => now(),
    ]);
    $placement->forceFill(['student_confirmed_at' => now()])->save();
    Storage::disk('local')->put($placement->acceptance_document_path, 'acceptance PDF');
    Storage::disk('local')->put($placement->placement_document_path, 'placement PDF');

    $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('SYSTEM ADMINISTRATION')
        ->assertSee('REG-100')
        ->assertSee('Student details are reviewed by the selected faculty.')
        ->assertDontSee('Create an account');

    $this->actingAs($admin)->post(route('admin.users.review', $student), [
        'decision' => 'approved',
        'notes' => 'Student identifiers checked against the faculty register.',
    ])->assertForbidden();

    $facultyHandler = User::factory()->create([
        'role' => 'faculty_handler',
        'faculty' => $student->faculty,
    ]);

    $this->actingAs($facultyHandler)->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('REG-100');
    $this->actingAs($facultyHandler)->post(route('faculty.students.review', $student), [
        'decision' => 'approved',
        'notes' => 'Student identifiers match the faculty register.',
    ])->assertRedirect();

    expect($student->fresh()->verification_status)->toBe('approved');
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $student->id,
        'type' => WorkspaceNotice::class,
    ]);

    $this->actingAs($admin)->post(route('admin.placements.review', $placement), [
        'decision' => 'approved',
        'notes' => 'Organization details match in both signed PDF letters.',
        'documents_match' => '1',
    ])->assertRedirect();

    expect($placement->fresh()->document_status)->toBe('approved');
    $this->actingAs($admin)->get(route('admin.placements.document', [$placement, 'acceptance']))->assertOk();
});

test('administrators provision student accounts with activation and forced password change', function () {
    \Illuminate\Support\Facades\Notification::fake();

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Mubs Student',
        'email' => 'student@mubs.ac.ug',
        'role' => 'student',
        'registration_number' => 'REG-2026-02',
        'student_number' => 'STU-2026-02',
    ])->assertRedirect();

    $student = User::query()->where('email', 'student@mubs.ac.ug')->firstOrFail();
    expect($student->must_change_password)->toBeTrue()
        ->and($student->course)->toBeNull()
        ->and($student->faculty)->toBeNull()
        ->and($student->hasVerifiedEmail())->toBeFalse()
        ->and($student->isApproved())->toBeFalse();
    $this->assertDatabaseMissing('users', ['email' => 'student@mubs.ac.ug', 'password' => 'password']);
    \Illuminate\Support\Facades\Notification::assertSentTo($student, \Illuminate\Auth\Notifications\VerifyEmail::class);

    $this->actingAs($student)->get(route('dashboard'))->assertRedirect();
});

test('administrators cannot provision students with non-university email addresses', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->from(route('admin.dashboard'))->post(route('admin.users.store'), [
        'name' => 'Non-Mubs Student',
        'email' => 'student@example.com',
        'role' => 'student',
        'course' => 'BACHELOR OF COMMERCE',
        'registration_number' => 'REG-INVALID-1',
        'student_number' => 'STU-INVALID-1',
    ])->assertRedirect(route('admin.dashboard'))->assertSessionHasErrors('email');

    $this->assertDatabaseMissing('users', ['email' => 'student@example.com']);
});

test('administrators can send announcements to faculty handlers and deans only', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $handler = User::factory()->create(['role' => 'faculty_handler', 'faculty' => 'Faculty of Commerce']);
    $dean = User::factory()->create(['role' => 'faculty_dean', 'faculty' => 'Faculty of Commerce']);
    $student = User::factory()->create(['role' => 'student']);
    $supervisor = User::factory()->create(['role' => 'field_supervisor']);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Faculty staff (handlers and deans)');

    $this->actingAs($admin)->post(route('admin.announcements.store'), [
        'audience' => 'faculty',
        'title' => 'Faculty review reminder',
        'message' => 'Please review pending student details.',
    ])->assertRedirect();

    foreach ([$handler, $dean] as $facultyUser) {
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $facultyUser->id,
            'type' => WorkspaceNotice::class,
        ]);
    }

    $this->assertDatabaseMissing('notifications', [
        'notifiable_id' => $student->id,
        'type' => WorkspaceNotice::class,
    ]);
    $this->assertDatabaseMissing('notifications', [
        'notifiable_id' => $supervisor->id,
        'type' => WorkspaceNotice::class,
    ]);
});

test('administrators can deactivate and reactivate non-admin accounts without deleting their history', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $otherAdmin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($admin)->patch(route('admin.users.status', $otherAdmin), [
        'is_active' => '0',
    ])->assertForbidden();

    $this->actingAs($admin)->patch(route('admin.users.status', $student), [
        'is_active' => '0',
    ])->assertRedirect();

    $this->assertDatabaseHas('users', ['id' => $student->id, 'is_active' => false]);

    $this->actingAs($student->fresh())->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->actingAs($admin)->patch(route('admin.users.status', $student), [
        'is_active' => '1',
    ])->assertRedirect();

    $this->assertDatabaseHas('users', ['id' => $student->id, 'is_active' => true]);
});

test('student confirmation is required before final placement approval', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create([
        'role' => 'student',
        'email' => 'confirm.student@mubs.ac.ug',
        'verification_status' => 'approved',
        'course' => 'BACHELOR OF COMMERCE',
        'registration_number' => 'REG-CONFIRM',
        'student_number' => 'STU-CONFIRM',
    ]);
    $placement = Placement::create([
        'student_id' => $student->id,
        'company_name' => 'Northstar Engineering',
        'acceptance_document_path' => 'acceptance/confirm.pdf',
        'placement_document_path' => 'placement/confirm.pdf',
        'start_date' => today()->subDay(),
        'end_date' => today()->addDays(30),
        'status' => 'active',
        'document_status' => 'pending',
    ]);
    Storage::disk('local')->put($placement->acceptance_document_path, 'acceptance');
    Storage::disk('local')->put($placement->placement_document_path, 'placement');

    $this->actingAs($admin)->post(route('admin.placements.review', $placement), [
        'decision' => 'approved',
        'notes' => 'Documents are complete.',
        'documents_match' => '1',
    ])->assertStatus(422);

    $this->actingAs($student)->post(route('placements.confirm', $placement))->assertRedirect(route('dashboard'));
    expect($placement->fresh()->student_confirmed_at)->not->toBeNull();
    $this->actingAs($admin)->post(route('admin.placements.review', $placement), [
        'decision' => 'approved',
        'notes' => 'Documents match and student confirmed the internship.',
        'documents_match' => '1',
    ])->assertRedirect();
    expect($placement->fresh()->document_status)->toBe('approved');
});

test('academic supervisors cannot exceed ten students in the same program', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $program = Program::query()->where('name', 'BACHELOR OF COMMERCE')->firstOrFail();
    $supervisor = User::factory()->create([
        'role' => 'academic_supervisor',
        'course' => $program->name,
        'email_verified_at' => now(),
    ]);

    foreach (range(1, 10) as $index) {
        $student = User::factory()->create([
            'role' => 'student',
            'course' => $program->name,
        ]);
        Placement::create([
            'student_id' => $student->id,
            'academic_supervisor_id' => $supervisor->id,
            'company_name' => 'Internship '.$index,
            'start_date' => today(),
            'end_date' => today()->addDays(30),
            'status' => 'active',
        ]);
    }

    $eleventhStudent = User::factory()->create([
        'role' => 'student',
        'course' => $program->name,
    ]);
    $eleventhPlacement = Placement::create([
        'student_id' => $eleventhStudent->id,
        'company_name' => 'Internship 11',
        'start_date' => today(),
        'end_date' => today()->addDays(30),
        'status' => 'active',
    ]);

    $this->actingAs($admin)->from(route('admin.dashboard'))->post(route('admin.placements.academic-supervisor', $eleventhPlacement), [
        'academic_supervisor_id' => $supervisor->id,
    ])->assertRedirect(route('admin.dashboard'))->assertSessionHasErrors('academic_supervisor_id');

    expect($eleventhPlacement->fresh()->academic_supervisor_id)->toBeNull();
});

test('faculty can send the invitation to the student-provided supervisor email', function () {
    Notification::fake();

    $facultyHandler = User::factory()->create([
        'role' => 'faculty_handler',
        'faculty' => 'Faculty of Commerce',
    ]);
    $otherFacultyHandler = User::factory()->create([
        'role' => 'faculty_handler',
        'faculty' => 'Faculty of Business',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'company_name' => 'Northstar Engineering',
        'faculty_name' => 'Faculty of Commerce',
        'field_supervisor_email' => 'supervisor@company.example',
        'start_date' => today(),
        'end_date' => today()->addDays(30),
        'status' => 'active',
    ]);

    $this->actingAs($admin)->post(route('faculty.placements.field-supervisor-invitation', $placement))->assertForbidden();
    $this->actingAs($otherFacultyHandler)->post(route('faculty.placements.field-supervisor-invitation', $placement))->assertNotFound();
    $this->actingAs($facultyHandler)->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('supervisor@company.example')
        ->assertSee('Send or resend invitation');

    $this->actingAs($facultyHandler)
        ->post(route('faculty.placements.field-supervisor-invitation', $placement))
        ->assertRedirect();

    $invitation = FieldSupervisorInvitation::query()->firstOrFail();
    expect($invitation->email)->toBe('supervisor@company.example')
        ->and($invitation->invited_by_id)->toBe($facultyHandler->id);
    Notification::assertSentOnDemand(\App\Notifications\FieldSupervisorInvitationNotice::class);
});

test('field supervisor invitation links activate a supervisor and connect the placement', function () {
    $student = User::factory()->create(['role' => 'student']);
    $facultyHandler = User::factory()->create([
        'role' => 'faculty_handler',
        'faculty' => 'Faculty of Commerce',
    ]);
    $placement = Placement::create([
        'student_id' => $student->id,
        'company_name' => 'Northstar Engineering',
        'faculty_name' => 'Faculty of Commerce',
        'field_supervisor_email' => 'supervisor@company.example',
        'start_date' => today(),
        'end_date' => today()->addDays(30),
        'status' => 'active',
    ]);
    $token = str_repeat('a', 64);
    $invitation = FieldSupervisorInvitation::query()->create([
        'placement_id' => $placement->id,
        'email' => 'supervisor@company.example',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addDays(7),
        'invited_by_id' => $facultyHandler->id,
    ]);

    $this->get(route('field-supervisor-invitations.show', $token))
        ->assertOk()
        ->assertSee('Field supervisor invitation');
    $this->post(route('field-supervisor-invitations.accept', $token), [
        'name' => 'Workplace Supervisor',
        'password' => 'A-secure-supervisor-password!42',
        'password_confirmation' => 'A-secure-supervisor-password!42',
    ])->assertRedirect(route('login'));

    $supervisor = User::query()->where('email', 'supervisor@company.example')->firstOrFail();
    expect($supervisor->role)->toBe('field_supervisor')
        ->and($supervisor->is_active)->toBeTrue()
        ->and($supervisor->hasVerifiedEmail())->toBeTrue()
        ->and($placement->fresh()->field_supervisor_id)->toBe($supervisor->id)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('temporary passwords must be changed before accessing the workspace', function () {
    $student = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($student)->get(route('dashboard'))
        ->assertRedirect(route('account.password.edit'));

    $this->actingAs($student)->get(route('account.password.edit'))->assertOk();
    $this->actingAs($student)->post(route('account.password.update'), [
        'password' => 'A-new-strong-password!42',
        'password_confirmation' => 'A-new-strong-password!42',
    ])->assertRedirect(route('dashboard'));

    expect($student->fresh()->must_change_password)->toBeFalse();
    $this->actingAs($student)->get(route('dashboard'))->assertOk();
});

test('daily report review moves from the field supervisor to the academic supervisor', function () {
    $field = User::factory()->create(['role' => 'field_supervisor']);
    $academic = User::factory()->create(['role' => 'academic_supervisor']);
    $student = User::factory()->create(['role' => 'student']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'field_supervisor_id' => $field->id,
        'academic_supervisor_id' => $academic->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
        'document_status' => 'approved',
    ]);
    $report = $placement->dailyReports()->create([
        'report_date' => '2026-11-03',
        'activities' => 'Prepared a daily field activity report for supervisor review.',
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);
    $assessment = [
        'rating' => 4,
        'strengths' => 'The student accurately documented their work.',
        'areas_for_improvement' => '',
        'overall_feedback' => 'The report is clear and shows steady progress.',
    ];

    $this->actingAs($academic)->post(route('reports.assessments.store', [$placement, $report]), $assessment)
        ->assertForbidden();

    $this->actingAs($field)->post(route('reports.assessments.store', [$placement, $report]), $assessment)
        ->assertRedirect(route('dashboard'));
    expect($report->fresh()->status)->toBe('field_approved');
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $academic->id]);

    $this->actingAs($academic)->post(route('reports.assessments.store', [$placement, $report]), $assessment)
        ->assertRedirect(route('dashboard'));
    expect($report->fresh()->status)->toBe('academic_reviewed');
});

test('academic supervisors can request and approve final report corrections', function () {
    Storage::fake('local');
    \Illuminate\Support\Carbon::setTestNow('2026-11-08 10:00:00');
    $academic = User::factory()->create(['role' => 'academic_supervisor']);
    $student = User::factory()->create([
        'role' => 'student',
        'email' => 'final.report@mubs.ac.ug',
    ]);
    $placement = Placement::create([
        'student_id' => $student->id,
        'academic_supervisor_id' => $academic->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2026-11-15',
        'status' => 'active',
        'document_status' => 'approved',
    ]);
    $templatePath = 'final-report-templates/template.pdf';
    Storage::disk('local')->put($templatePath, 'template');
    $placement->forceFill([
        'final_report_template_path' => $templatePath,
        'report_template_sent_at' => now(),
    ])->save();

    $this->actingAs($student)->get(route('placements.final-report-template.download', $placement))->assertOk();
    $this->actingAs($student)->post(route('placements.final-report.store', $placement), [
        'final_report' => UploadedFile::fake()->create('final-report.pdf', 40, 'application/pdf'),
    ])->assertRedirect();

    $report = $placement->studentFinalReports()->firstOrFail();
    $this->actingAs($academic)->post(route('final-reports.review', $report), [
        'decision' => 'revision_requested',
        'reviewer_notes' => 'Please add the missing reflection section.',
    ])->assertRedirect();
    expect($report->fresh()->status)->toBe('revision_requested');

    $this->actingAs($student)->post(route('placements.final-report.store', $placement), [
        'final_report' => UploadedFile::fake()->create('revised-report.pdf', 40, 'application/pdf'),
    ])->assertRedirect();
    $revisedReport = $placement->studentFinalReports()->latest('submitted_at')->firstOrFail();
    $this->actingAs($academic)->post(route('final-reports.review', $revisedReport), [
        'decision' => 'approved',
        'reviewer_notes' => 'The revised report is complete.',
    ])->assertRedirect();
    expect($revisedReport->fresh()->status)->toBe('approved');

    \Illuminate\Support\Carbon::setTestNow();
});

test('daily reports collect all learning fields and students see field comments but not assessment marks', function () {
    $fieldSupervisor = User::factory()->create(['role' => 'field_supervisor', 'verification_status' => 'approved']);
    $academicSupervisor = User::factory()->create(['role' => 'academic_supervisor', 'verification_status' => 'approved']);
    $student = User::factory()->create(['role' => 'student', 'verification_status' => 'approved']);
    $start = now()->startOfWeek();
    $placement = Placement::create([
        'student_id' => $student->id,
        'field_supervisor_id' => $fieldSupervisor->id,
        'academic_supervisor_id' => $academicSupervisor->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => $start,
        'end_date' => $start->copy()->addDays(4),
        'status' => 'active',
        'document_status' => 'approved',
    ]);
    $placement->schedules()->create([
        'day_of_week' => $start->dayOfWeekIso,
        'start_time' => '08:00',
        'end_time' => '17:00',
    ]);

    $this->actingAs($student)->post(route('reports.store', $placement), [
        'report_date' => $start->toDateString(),
        'activities' => 'Updated the equipment maintenance register with the engineering team.',
        'challenges' => 'Several measurements needed to be checked a second time.',
        'skills_acquired' => 'Practised documenting a maintenance inspection.',
        'lessons_learned' => 'Record measurements while the equipment is still accessible.',
    ])->assertRedirect(route('dashboard'));

    $report = $placement->dailyReports()->firstOrFail();
    $report->assessments()->create([
        'placement_id' => $placement->id,
        'supervisor_id' => $fieldSupervisor->id,
        'rating' => 4,
        'strengths' => 'Follows the inspection checklist and communicates clearly.',
        'areas_for_improvement' => 'Continue improving confidence with technical measurements.',
        'overall_feedback' => 'Good progress during this workday.',
        'submitted_at' => now(),
    ]);
    $report->comments()->create([
        'author_id' => $fieldSupervisor->id,
        'body' => 'Good use of the inspection checklist today.',
    ]);
    $report->comments()->create([
        'author_id' => $academicSupervisor->id,
        'body' => 'This academic-only comment must not appear to the student.',
    ]);

    $this->actingAs($student)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Good use of the inspection checklist today.')
        ->assertDontSee('4 / 5')
        ->assertDontSee('Good progress during this workday.')
        ->assertDontSee('This academic-only comment must not appear to the student.');

    $this->actingAs($academicSupervisor)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('4 / 5')
        ->assertSee('Good use of the inspection checklist today.')
        ->assertSee('This academic-only comment must not appear to the student.');
});

test('only the assigned academic supervisor can schedule meetings and both participants are notified', function () {
    $academic = User::factory()->create(['role' => 'academic_supervisor']);
    $field = User::factory()->create(['role' => 'field_supervisor']);
    $student = User::factory()->create(['role' => 'student']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'academic_supervisor_id' => $academic->id,
        'field_supervisor_id' => $field->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
    ]);
    $startsAt = now()->addDays(2)->startOfHour();

    $this->actingAs($field)->post(route('placements.meetings.store', $placement), [
        'mode' => 'google_meet',
        'starts_at' => $startsAt->toDateTimeLocalString(),
        'ends_at' => $startsAt->copy()->addHour()->toDateTimeLocalString(),
        'teams_url' => 'https://meet.google.com/abc-defg-hij',
        'notes' => 'Mid-placement review.',
    ])->assertForbidden();

    $this->actingAs($academic)->post(route('placements.meetings.store', $placement), [
        'mode' => 'google_meet',
        'starts_at' => $startsAt->toDateTimeLocalString(),
        'ends_at' => $startsAt->copy()->addHour()->toDateTimeLocalString(),
        'teams_url' => 'https://meet.google.com/abc-defg-hij',
        'notes' => 'Mid-placement review.',
    ])->assertRedirect();

    $this->assertDatabaseHas('supervisor_meetings', ['placement_id' => $placement->id, 'mode' => 'google_meet']);
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $student->id]);
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $field->id]);
});

test('academic final assessment publishes a private PDF report and hides scores from students', function () {
    Storage::fake('local');
    $academic = User::factory()->create(['role' => 'academic_supervisor']);
    $student = User::factory()->create(['role' => 'student']);
    $field = User::factory()->create(['role' => 'field_supervisor']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'field_supervisor_id' => $field->id,
        'academic_supervisor_id' => $academic->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => today()->subDays(30),
        'end_date' => today()->subDay(),
        'status' => 'active',
    ]);
    $placement->finalAssessments()->create([
        'supervisor_id' => $field->id,
        'supervisor_role' => 'field_supervisor',
        'overall_score' => 80,
        'feedback' => 'The student met the workplace expectations.',
        'submitted_at' => now(),
    ]);

    $this->actingAs($academic)->post(route('placements.final-assessments.store', $placement), [
        'overall_score' => 88,
        'physical_score' => 85,
        'teams_score' => 91,
        'feedback' => 'The student demonstrated strong professional growth and reflection.',
        'faculty_report' => UploadedFile::fake()->create('faculty-report.pdf', 50, 'application/pdf'),
        'assessment_confirmed' => '1',
    ])->assertRedirect();

    $this->assertDatabaseHas('placement_assessments', [
        'placement_id' => $placement->id,
        'supervisor_id' => $academic->id,
        'overall_score' => 88,
        'physical_score' => 85,
        'teams_score' => 91,
    ]);

    $this->actingAs($student)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Download faculty report (PDF)')
        ->assertDontSee('88/100')
        ->assertDontSee('85/100')
        ->assertDontSee('91/100');

    $this->actingAs($student)->get(route('placements.faculty-report', $placement))->assertOk();
});

test('field supervisors can submit final marks only during the assigned placement', function () {
    $field = User::factory()->create(['role' => 'field_supervisor', 'verification_status' => 'approved']);
    $academic = User::factory()->create(['role' => 'academic_supervisor', 'verification_status' => 'approved']);
    $student = User::factory()->create(['role' => 'student', 'verification_status' => 'approved']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'field_supervisor_id' => $field->id,
        'academic_supervisor_id' => $academic->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => today()->subDay(),
        'end_date' => today()->addDay(),
        'status' => 'active',
        'document_status' => 'approved',
    ]);

    $this->actingAs($field)->post(route('placements.final-assessments.store', $placement), [
        'overall_score' => 77,
        'feedback' => 'The student is dependable and follows the workplace safety practices.',
    ])->assertRedirect();

    $this->actingAs($student)->get(route('dashboard'))->assertDontSee('77.00/100');
    $this->actingAs($academic)->get(route('dashboard'))->assertSee('Field supervisor · 77.00/100');
});

test('help and contact are available and contact messages are sent to the admin queue', function () {
    $this->get(route('help'))->assertOk()->assertSee('How can we help?');
    $this->get(route('contact'))->assertOk()->assertSee('Contact us');

    $this->post(route('contact.store'), [
        'name' => 'Placement Student',
        'email' => 'student@example.com',
        'subject' => 'Placement approval',
        'message' => 'Please let me know when my placement documents are reviewed.',
    ])->assertRedirect();

    $this->assertDatabaseHas('support_requests', [
        'email' => 'student@example.com',
        'subject' => 'Placement approval',
        'status' => 'open',
    ]);
});

test('assigned supervisors can message one another but students cannot use supervisor chat', function () {
    $academic = User::factory()->create(['role' => 'academic_supervisor']);
    $field = User::factory()->create(['role' => 'field_supervisor']);
    $student = User::factory()->create(['role' => 'student']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'academic_supervisor_id' => $academic->id,
        'field_supervisor_id' => $field->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
    ]);

    $this->actingAs($student)->post(route('placements.messages.store', $placement), [
        'body' => 'A student cannot use the supervisor-only chat.',
    ])->assertForbidden();

    $this->actingAs($academic)->post(route('placements.messages.store', $placement), [
        'body' => 'Can we confirm the next joint supervision meeting?',
    ])->assertRedirect();

    $this->assertDatabaseHas('supervisor_messages', [
        'placement_id' => $placement->id,
        'sender_id' => $academic->id,
        'recipient_id' => $field->id,
    ]);
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $field->id]);
});

test('students can correct identifiers and are returned to their faculty review queue', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $facultyHandler = User::factory()->create([
        'role' => 'faculty_handler',
        'faculty' => 'Faculty of Management',
    ]);
    $student = User::factory()->create([
        'role' => 'student',
        'email' => 'identity.student@mubs.ac.ug',
        'course' => 'BACHELOR OF COMMERCE',
        'registration_number' => 'REG-OLD',
        'student_number' => 'STU-OLD',
        'verification_status' => 'rejected',
        'verification_notes' => 'Please confirm the identifiers.',
    ]);

    $this->actingAs($student)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Select your course')
        ->assertSee('Select your faculty')
        ->assertSee('Faculty of Management');

    $this->actingAs($student)->put(route('student.identity.update'), [
        'course' => 'BACHELOR OF INTERNATIONAL BUSINESS',
        'faculty' => 'Faculty of Management',
        'registration_number' => 'REG-NEW',
        'student_number' => 'STU-NEW',
    ])->assertRedirect();

    expect($student->fresh())
        ->course->toBe('BACHELOR OF INTERNATIONAL BUSINESS')
        ->faculty->toBe('Faculty of Management')
        ->registration_number->toBe('REG-NEW')
        ->student_number->toBe('STU-NEW')
        ->verification_status->toBe('pending')
        ->verification_notes->toBeNull();

    $this->assertDatabaseHas('notifications', ['notifiable_id' => $facultyHandler->id]);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $admin->id]);
});

test('student placement document uploads accept only PDF letters', function () {
    Storage::fake('local');
    $student = User::factory()->create(['role' => 'student', 'verification_status' => 'approved']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => today()->subDay(),
        'end_date' => today()->addDays(30),
        'status' => 'active',
        'document_status' => 'pending',
    ]);

    $this->actingAs($student)->from(route('dashboard'))->post(route('placements.documents.store', $placement), [
        'acceptance_document' => UploadedFile::fake()->create('acceptance.png', 20, 'image/png'),
        'placement_document' => UploadedFile::fake()->create('placement.pdf', 20, 'application/pdf'),
    ])->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('acceptance_document');

    expect($placement->fresh()->acceptance_document_path)->toBeNull();
});
