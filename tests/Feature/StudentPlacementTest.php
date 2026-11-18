<?php

use App\Models\Placement;
use App\Models\User;
use App\Notifications\WorkspaceNotice;

test('guests must sign in before accessing student tools', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('a student can view the workspace while waiting to register a mapped placement', function () {
    $student = User::factory()->create([
        'role' => 'student',
        'course' => 'BACHELOR OF COMMERCE',
    ]);

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Submit your internship details and supporting letters')
        ->assertSee('Submit to faculty');
});

    test('the legacy placement creation paths remain unavailable', function () {
    $student = User::factory()->create(['role' => 'student', 'verification_status' => 'approved']);

    $this->actingAs($student)->get('/placements/create')->assertNotFound();
    $this->actingAs($student)->post('/placements', [])->assertNotFound();
});

test('a student can request a field supervisor invitation by email', function () {
    $student = User::factory()->create(['role' => 'student']);
    $facultyHandler = User::factory()->create([
        'role' => 'faculty_handler',
        'faculty' => 'Faculty of Commerce',
    ]);
    $facultyDean = User::factory()->create([
        'role' => 'faculty_dean',
        'faculty' => 'Faculty of Commerce',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    $supervisor = User::factory()->create([
        'email' => 'field.supervisor@example.com',
        'role' => 'field_supervisor',
    ]);
    $placement = Placement::create([
        'student_id' => $student->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
        'faculty_name' => 'Faculty of Commerce',
    ]);

    $this->actingAs($student)
        ->post(route('placements.field-supervisor', $placement), [
            'field_supervisor_email' => 'FIELD.SUPERVISOR@example.com',
        ])
        ->assertRedirect(route('dashboard'));

    expect($placement->fresh()->field_supervisor_id)->toBeNull()
        ->and($placement->fresh()->field_supervisor_email)->toBe('field.supervisor@example.com');
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $facultyHandler->id,
        'type' => WorkspaceNotice::class,
    ]);
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $facultyDean->id,
        'type' => WorkspaceNotice::class,
    ]);
    $this->assertDatabaseMissing('notifications', [
        'notifiable_id' => $admin->id,
        'type' => WorkspaceNotice::class,
    ]);
});

test('a field supervisor invitation can be requested before an account exists', function () {
    $student = User::factory()->create(['role' => 'student']);
    User::factory()->create([
        'role' => 'faculty_handler',
        'faculty' => 'Faculty of Commerce',
    ]);
    $otherAccount = User::factory()->create(['role' => 'academic_supervisor']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
        'faculty_name' => 'Faculty of Commerce',
    ]);

    $this->actingAs($student)
        ->from(route('dashboard'))
        ->post(route('placements.field-supervisor', $placement), [
            'field_supervisor_email' => $otherAccount->email,
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    expect($placement->fresh()->field_supervisor_id)->toBeNull()
        ->and($placement->fresh()->field_supervisor_email)->toBe($otherAccount->email);
});

test('students cannot self-assign academic supervisors', function () {
    $student = User::factory()->create(['role' => 'student']);
    $supervisor = User::factory()->create([
        'email' => 'academic.supervisor@school.example',
        'role' => 'academic_supervisor',
    ]);
    $placement = Placement::create([
        'student_id' => $student->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
    ]);

    $this->actingAs($student)->post(route('admin.placements.academic-supervisor', $placement), [
        'academic_supervisor_id' => $supervisor->id,
    ])->assertForbidden();
    expect($placement->fresh()->academic_supervisor_id)->toBeNull();
});

test('only an administrator can assign an academic supervisor', function () {
    $student = User::factory()->create(['role' => 'student']);
    $fieldSupervisor = User::factory()->create(['role' => 'field_supervisor']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
    ]);

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->post(route('admin.placements.academic-supervisor', $placement), [
        'academic_supervisor_id' => $fieldSupervisor->id,
    ])->assertStatus(422);
    expect($placement->fresh()->academic_supervisor_id)->toBeNull();
});

test('a student cannot connect a field supervisor to another student placement', function () {
    $owner = User::factory()->create(['role' => 'student']);
    $otherStudent = User::factory()->create(['role' => 'student']);
    $placement = Placement::create([
        'student_id' => $owner->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
    ]);

    $this->actingAs($otherStudent)
        ->post(route('placements.field-supervisor', $placement), [
            'field_supervisor_email' => 'field.supervisor@example.com',
        ])
        ->assertForbidden();
});

test('a field supervisor can view only connected student placements and their reports', function () {
    $supervisor = User::factory()->create(['role' => 'field_supervisor']);
    $student = User::factory()->create(['name' => 'Assigned Student', 'role' => 'student']);
    $otherStudent = User::factory()->create(['name' => 'Unassigned Student', 'role' => 'student']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'field_supervisor_id' => $supervisor->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
    ]);
    Placement::create([
        'student_id' => $otherStudent->id,
        'company_name' => 'Private Placement',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
    ]);
    $placement->dailyReports()->create([
        'report_date' => '2026-11-03',
        'activities' => 'Prepared a materials estimate with the site team.',
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Assigned Student')
        ->assertSee('Northstar Engineering')
        ->assertSee('Prepared a materials estimate with the site team.')
        ->assertSee('Submit assessment')
        ->assertDontSee('Unassigned Student')
        ->assertDontSee('Private Placement');
});

test('an assigned field supervisor can submit an assessment', function () {
    $supervisor = User::factory()->create(['role' => 'field_supervisor']);
    $student = User::factory()->create(['role' => 'student']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'field_supervisor_id' => $supervisor->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
    ]);
    $report = $placement->dailyReports()->create([
        'report_date' => '2026-11-03',
        'activities' => 'Completed a field task and submitted the daily log.',
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    $this->actingAs($supervisor)
        ->post(route('reports.assessments.store', [$placement, $report]), [
            'rating' => 4,
            'strengths' => 'Communicates clearly and follows site safety procedures.',
            'areas_for_improvement' => 'Continue developing confidence with cost estimates.',
            'overall_feedback' => 'Showing strong, consistent progress across assigned work.',
        ])
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('supervisor_assessments', [
        'placement_id' => $placement->id,
        'daily_report_id' => $report->id,
        'supervisor_id' => $supervisor->id,
        'rating' => 4,
    ]);
});

test('an assigned academic supervisor can submit an assessment', function () {
    $supervisor = User::factory()->create(['role' => 'academic_supervisor']);
    $student = User::factory()->create(['role' => 'student']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'academic_supervisor_id' => $supervisor->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
    ]);
    $report = $placement->dailyReports()->create([
        'report_date' => '2026-11-03',
        'activities' => 'Submitted a report reflecting on the skills applied this week.',
        'status' => 'field_approved',
        'submitted_at' => now(),
    ]);
    $fieldSupervisor = User::factory()->create(['role' => 'field_supervisor']);
    $report->assessments()->create([
        'placement_id' => $placement->id,
        'supervisor_id' => $fieldSupervisor->id,
        'rating' => 4,
        'strengths' => 'The student accurately documented their work.',
        'overall_feedback' => 'The report is clear and shows steady progress.',
        'submitted_at' => now(),
    ]);

    $this->actingAs($supervisor)
        ->post(route('reports.assessments.store', [$placement, $report]), [
            'rating' => 5,
            'strengths' => 'Applies course concepts accurately in the weekly reports.',
            'areas_for_improvement' => '',
            'overall_feedback' => 'Excellent engagement and reflective learning throughout.',
        ])
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('supervisor_assessments', [
        'placement_id' => $placement->id,
        'daily_report_id' => $report->id,
        'supervisor_id' => $supervisor->id,
        'rating' => 5,
    ]);
});

test('students cannot submit supervisor assessments', function () {
    $student = User::factory()->create(['role' => 'student']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
    ]);
    $report = $placement->dailyReports()->create([
        'report_date' => '2026-11-03',
        'activities' => 'Student submitted work for supervisor review.',
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    $this->actingAs($student)
        ->post(route('reports.assessments.store', [$placement, $report]), [
            'rating' => 5,
            'strengths' => 'This should not be accepted from a student account.',
            'overall_feedback' => 'This should not be accepted from a student account.',
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('supervisor_assessments', 0);
});

test('a supervisor cannot assess a placement assigned to another supervisor', function () {
    $supervisor = User::factory()->create(['role' => 'field_supervisor']);
    $assignedSupervisor = User::factory()->create(['role' => 'field_supervisor']);
    $student = User::factory()->create(['role' => 'student']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'field_supervisor_id' => $assignedSupervisor->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
    ]);
    $report = $placement->dailyReports()->create([
        'report_date' => '2026-11-03',
        'activities' => 'Submitted activities remain private from unassigned supervisors.',
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    $this->actingAs($supervisor)
        ->post(route('reports.assessments.store', [$placement, $report]), [
            'rating' => 3,
            'strengths' => 'Not authorized to assess this student placement.',
            'overall_feedback' => 'Not authorized to assess this student placement.',
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('supervisor_assessments', 0);
});

test('a supervisor cannot assess a draft daily report', function () {
    $supervisor = User::factory()->create(['role' => 'field_supervisor']);
    $student = User::factory()->create(['role' => 'student']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'field_supervisor_id' => $supervisor->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
    ]);
    $draft = $placement->dailyReports()->create([
        'report_date' => '2026-11-03',
        'activities' => 'Work in progress and not yet submitted.',
        'status' => 'draft',
    ]);

    $this->actingAs($supervisor)
        ->post(route('reports.assessments.store', [$placement, $draft]), [
            'rating' => 4,
            'strengths' => 'This assessment must wait for report submission.',
            'overall_feedback' => 'This assessment must wait for report submission.',
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('supervisor_assessments', 0);
});

test('a student can submit a daily report for a scheduled workday', function () {
    $student = User::factory()->create();
    $start = now()->startOfWeek();
    $placement = Placement::create([
        'student_id' => $student->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => $start,
        'end_date' => $start->copy()->addDays(4),
        'status' => 'active',
    ]);
    $placement->schedules()->create([
        'day_of_week' => $start->dayOfWeekIso,
        'start_time' => '08:00',
        'end_time' => '17:00',
    ]);

    $response = $this->actingAs($student)->post(route('reports.store', $placement), [
        'report_date' => $start->toDateString(),
        'activities' => 'Reviewed project drawings and updated the site progress register.',
        'challenges' => 'Coordinated a late drawing revision with the site team.',
        'skills_acquired' => 'Practised checking measurements against the project specification.',
        'lessons_learned' => 'Confirm the latest drawing revision before starting a task.',
    ]);

    $response->assertRedirect(route('dashboard'));
    expect($placement->dailyReports()->whereDate('report_date', $start->toDateString())->where('status', 'submitted')->exists())->toBeTrue();
});

test('a student cannot submit reports to another student placement', function () {
    $owner = User::factory()->create();
    $otherStudent = User::factory()->create();
    $placement = Placement::create([
        'student_id' => $owner->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => now()->startOfWeek(),
        'end_date' => now()->startOfWeek()->addDays(4),
        'status' => 'active',
    ]);

    $this->actingAs($otherStudent)
        ->post(route('reports.store', $placement), [
            'report_date' => now()->startOfWeek()->toDateString(),
            'activities' => 'This report should not be associated with another student.',
            'challenges' => 'This report should not be accepted.',
            'skills_acquired' => 'This report should not be accepted.',
            'lessons_learned' => 'This report should not be accepted.',
        ])
        ->assertForbidden();
});
