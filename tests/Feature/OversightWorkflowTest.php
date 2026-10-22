<?php

use App\Models\Placement;
use App\Models\User;
use App\Notifications\WorkspaceNotice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('only administrators can review accounts and placement documents', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create([
        'role' => 'student',
        'verification_status' => 'pending',
        'course' => 'Information Technology',
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
    ]);
    Storage::disk('local')->put($placement->acceptance_document_path, 'acceptance PDF');
    Storage::disk('local')->put($placement->placement_document_path, 'placement PDF');

    $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('SYSTEM ADMINISTRATION')
        ->assertSee('REG-100');

    $this->actingAs($admin)->post(route('admin.users.review', $student), [
        'decision' => 'approved',
        'notes' => 'Student identifiers checked against the faculty register.',
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
        'mode' => 'teams',
        'starts_at' => $startsAt->toDateTimeLocalString(),
        'ends_at' => $startsAt->copy()->addHour()->toDateTimeLocalString(),
        'teams_url' => 'https://teams.microsoft.com/l/meetup-join/example',
        'notes' => 'Mid-placement review.',
    ])->assertForbidden();

    $this->actingAs($academic)->post(route('placements.meetings.store', $placement), [
        'mode' => 'teams',
        'starts_at' => $startsAt->toDateTimeLocalString(),
        'ends_at' => $startsAt->copy()->addHour()->toDateTimeLocalString(),
        'teams_url' => 'https://teams.microsoft.com/l/meetup-join/example',
        'notes' => 'Mid-placement review.',
    ])->assertRedirect();

    $this->assertDatabaseHas('supervisor_meetings', ['placement_id' => $placement->id, 'mode' => 'teams']);
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $student->id]);
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $field->id]);
});

test('academic final assessment publishes a private PDF report and hides scores from students', function () {
    Storage::fake('local');
    $academic = User::factory()->create(['role' => 'academic_supervisor']);
    $student = User::factory()->create(['role' => 'student']);
    $placement = Placement::create([
        'student_id' => $student->id,
        'academic_supervisor_id' => $academic->id,
        'company_name' => 'Northstar Engineering',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'status' => 'active',
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

test('students can correct identifiers and are returned to the administrator review queue', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create([
        'role' => 'student',
        'course' => 'Old course',
        'registration_number' => 'REG-OLD',
        'student_number' => 'STU-OLD',
        'verification_status' => 'rejected',
        'verification_notes' => 'Please confirm the identifiers.',
    ]);

    $this->actingAs($student)->put(route('student.identity.update'), [
        'course' => 'Information Technology',
        'registration_number' => 'REG-NEW',
        'student_number' => 'STU-NEW',
    ])->assertRedirect();

    expect($student->fresh())
        ->course->toBe('Information Technology')
        ->registration_number->toBe('REG-NEW')
        ->student_number->toBe('STU-NEW')
        ->verification_status->toBe('pending')
        ->verification_notes->toBeNull();

    $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id]);
});

test('placement uploads only accept PDF letters', function () {
    Storage::fake('local');
    $student = User::factory()->create(['role' => 'student', 'verification_status' => 'approved']);

    $this->actingAs($student)->from(route('placements.create'))->post(route('placements.store'), [
        'company_name' => 'Northstar Engineering',
        'acceptance_document' => UploadedFile::fake()->create('acceptance.png', 20, 'image/png'),
        'placement_document' => UploadedFile::fake()->create('placement.pdf', 20, 'application/pdf'),
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-29',
        'workdays' => ['1', '2', '3', '4', '5'],
        'start_time' => '08:00',
        'end_time' => '17:00',
    ])->assertRedirect(route('placements.create'))
        ->assertSessionHasErrors('acceptance_document');

    $this->assertDatabaseCount('placements', 0);
});
