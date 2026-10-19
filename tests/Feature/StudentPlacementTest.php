<?php

use App\Models\Placement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('guests must sign in before accessing student tools', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('placements.create'))->assertRedirect(route('login'));
    $this->post(route('placements.store'))->assertRedirect(route('login'));
});

test('a student can view the workspace and placement setup form', function () {
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Set up your placement')
        ->assertSee('Every workday, accounted for.');

    $this->get(route('placements.create'))
        ->assertOk()
        ->assertSee('Company acceptance letter')
        ->assertSee('Weekly work schedule')
        ->assertSee('Every workday, accounted for.');
});

test('a student can save placement details and a weekly schedule', function () {
    Storage::fake('local');
    $student = User::factory()->create();

    $response = $this->actingAs($student)->post(route('placements.store'), [
        'company_name' => 'Northstar Engineering',
        'company_address' => '12 Market Road',
        'acceptance_document' => UploadedFile::fake()->create('acceptance.pdf', 40, 'application/pdf'),
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'workdays' => ['1', '2', '3', '4', '5'],
        'start_time' => '08:00',
        'end_time' => '17:00',
    ]);

    $response->assertRedirect(route('dashboard'));
    $placement = Placement::where('student_id', $student->id)->firstOrFail();
    expect($placement->schedules)->toHaveCount(5);
    Storage::disk('local')->assertExists($placement->acceptance_document_path);
});

test('a student can connect their registered field supervisor by email', function () {
    $student = User::factory()->create(['role' => 'student']);
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
    ]);

    $this->actingAs($student)
        ->post(route('placements.field-supervisor', $placement), [
            'field_supervisor_email' => 'FIELD.SUPERVISOR@example.com',
        ])
        ->assertRedirect(route('dashboard'));

    expect($placement->fresh()->field_supervisor_id)->toBe($supervisor->id);
});

test('a student cannot connect an account that is not a field supervisor', function () {
    $student = User::factory()->create(['role' => 'student']);
    $otherAccount = User::factory()->create(['role' => 'academic_supervisor']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
    ]);

    $this->actingAs($student)
        ->from(route('dashboard'))
        ->post(route('placements.field-supervisor', $placement), [
            'field_supervisor_email' => $otherAccount->email,
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('field_supervisor_email');

    expect($placement->fresh()->field_supervisor_id)->toBeNull();
});

test('a student can connect their assigned academic supervisor by email', function () {
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

    $this->actingAs($student)
        ->post(route('placements.academic-supervisor', $placement), [
            'academic_supervisor_email' => 'ACADEMIC.SUPERVISOR@school.example',
        ])
        ->assertRedirect(route('dashboard'));

    expect($placement->fresh()->academic_supervisor_id)->toBe($supervisor->id);
});

test('a student cannot connect a non-academic account as their academic supervisor', function () {
    $student = User::factory()->create(['role' => 'student']);
    $fieldSupervisor = User::factory()->create(['role' => 'field_supervisor']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
    ]);

    $this->actingAs($student)
        ->from(route('dashboard'))
        ->post(route('placements.academic-supervisor', $placement), [
            'academic_supervisor_email' => $fieldSupervisor->email,
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('academic_supervisor_email');

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
        'status' => 'submitted',
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
        ])
        ->assertForbidden();
});