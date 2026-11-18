<?php

use App\Models\Placement;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('faculty handlers and deans can approve pending students in their own faculty', function () {
    $program = Program::query()->where('name', 'BACHELOR OF COMMERCE')->firstOrFail();
    $student = User::factory()->create([
        'role' => 'student',
        'email' => 'faculty-review.student@mubs.ac.ug',
        'course' => $program->name,
        'faculty' => 'Faculty of Management',
        'registration_number' => 'REG-FAC-REVIEW',
        'student_number' => 'STU-FAC-REVIEW',
        'verification_status' => 'pending',
    ]);
    $studentForDean = User::factory()->create([
        'role' => 'student',
        'email' => 'faculty-dean-review.student@mubs.ac.ug',
        'course' => $program->name,
        'faculty' => 'Faculty of Management',
        'registration_number' => 'REG-DEAN-REVIEW',
        'student_number' => 'STU-DEAN-REVIEW',
        'verification_status' => 'pending',
    ]);
    $handler = User::factory()->create([
        'role' => 'faculty_handler',
        'faculty' => 'Faculty of Management',
    ]);
    $dean = User::factory()->create([
        'role' => 'faculty_dean',
        'faculty' => 'Faculty of Management',
    ]);
    $otherFacultyHandler = User::factory()->create([
        'role' => 'faculty_handler',
        'faculty' => 'Faculty of Commerce',
    ]);

    $this->actingAs($otherFacultyHandler)->post(route('faculty.students.review', $student), [
        'decision' => 'approved',
        'notes' => 'Reviewing the student details.',
    ])->assertNotFound();

    $this->actingAs($handler)->post(route('faculty.students.review', $student), [
        'decision' => 'approved',
        'notes' => 'Student details match the faculty records.',
    ])->assertRedirect();
    expect($student->fresh()->verification_status)->toBe('approved')
        ->and($student->fresh()->verified_by_id)->toBe($handler->id);

    $this->actingAs($dean)->post(route('faculty.students.review', $studentForDean), [
        'decision' => 'approved',
        'notes' => 'Student details match the faculty records.',
    ])->assertRedirect();
    expect($studentForDean->fresh()->verification_status)->toBe('approved')
        ->and($studentForDean->fresh()->verified_by_id)->toBe($dean->id);
});

test('students choose their faculty and the dean for that faculty makes the final decision', function () {
    Storage::fake('local');
    $program = Program::query()->where('name', 'BACHELOR OF COMMERCE')->firstOrFail();
    $program->forceFill(['faculty' => null])->save();
    $student = User::factory()->create([
        'role' => 'student',
        'email' => 'faculty-flow.student@mubs.ac.ug',
        'verification_status' => 'approved',
        'course' => $program->name,
        'faculty' => 'Faculty of Management',
        'registration_number' => 'REG-FLOW-100',
        'student_number' => 'STU-FLOW-100',
    ]);
    $handler = User::factory()->create([
        'role' => 'faculty_handler',
        'faculty' => $student->faculty,
        'email' => 'management.handler@example.test',
    ]);
    $dean = User::factory()->create([
        'role' => 'faculty_dean',
        'faculty' => $student->faculty,
        'email' => 'management.dean@example.test',
    ]);
    $otherFacultyHandler = User::factory()->create([
        'role' => 'faculty_handler',
        'faculty' => 'Faculty of Commerce',
        'email' => 'commerce.handler@example.test',
    ]);

    $this->actingAs($student)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Submit to faculty')
        ->assertSee('Faculty of Management');

    $this->actingAs($student)->from(route('dashboard'))->post(route('placements.register'), [
        'company_name' => 'Northstar Engineering',
        'company_address' => '12 Market Road',
        'start_date' => today()->addDays(2)->toDateString(),
        'end_date' => today()->addDays(32)->toDateString(),
        'workdays' => ['1', '2', '3', '4', '5'],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'acceptance_document' => UploadedFile::fake()->create('acceptance.pdf', 40, 'application/pdf'),
        'placement_document' => UploadedFile::fake()->create('faculty-letter.pdf', 40, 'application/pdf'),
    ])->assertRedirect(route('dashboard'))
        ->assertSessionHas('status', 'Your placement has been submitted to Faculty of Management for confirmation.');

    $placement = $student->placements()->firstOrFail();
    expect($placement->faculty_name)->toBe($student->faculty)
        ->and($placement->faculty_review_status)->toBe('submitted')
        ->and($placement->schedules)->toHaveCount(5);

    $this->actingAs($student)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Your placement has been submitted to Faculty of Management for confirmation.')
        ->assertSee('Daily activities open after your faculty dean confirms your placement.')
        ->assertDontSee('name="activities"', false);
    $this->actingAs($student)->post(route('reports.store', $placement), [])
        ->assertForbidden();

    $this->actingAs($handler)->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Northstar Engineering');
    $this->actingAs($otherFacultyHandler)->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertDontSee('Northstar Engineering');

    $this->actingAs($handler)->post(route('faculty.placements.verification-request', $placement), [
        'request_message' => 'Confirm the company and internship dates are correct.',
    ])->assertRedirect();
    expect($placement->fresh()->faculty_review_status)->toBe('verification_requested');

    $this->actingAs($student)->post(route('placements.verification-response', $placement), [
        'decision' => 'correction_requested',
        'response' => 'The company address should be 14 Market Road.',
    ])->assertRedirect(route('dashboard'));
    expect($placement->fresh()->faculty_review_status)->toBe('correction_requested');

    $this->actingAs($handler)->put(route('faculty.placements.update', $placement), [
        'company_name' => 'Northstar Engineering',
        'company_address' => '14 Market Road',
        'start_date' => today()->addDays(2)->toDateString(),
        'end_date' => today()->addDays(32)->toDateString(),
        'workdays' => ['1', '2', '3', '4', '5'],
        'start_time' => '08:00',
        'end_time' => '17:00',
    ])->assertRedirect();
    expect($placement->fresh()->student_verification_response)->toBeNull();

    $this->actingAs($handler)->post(route('faculty.placements.verification-request', $placement), [
        'request_message' => 'The address has been corrected. Confirm the revised details.',
    ])->assertRedirect();

    $this->actingAs($student)->post(route('placements.verification-response', $placement), [
        'decision' => 'confirmed',
        'response' => 'I confirm the revised company address and internship dates.',
    ])->assertRedirect(route('dashboard'));
    expect($placement->fresh()->faculty_review_status)->toBe('student_confirmed');

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->post(route('admin.placements.review', $placement), [
        'decision' => 'approved',
        'notes' => 'Admin must not replace dean review.',
        'documents_match' => '1',
    ])->assertForbidden();
    $this->actingAs($admin)->get(route('admin.dashboard', ['faculty' => $student->faculty]))
        ->assertOk()
        ->assertSee('Northstar Engineering');

    $this->actingAs($dean)->post(route('faculty.placements.dean-review', $placement), [
        'decision' => 'approved',
        'notes' => 'Student confirmation and both letters are verified.',
    ])->assertRedirect();

    expect($placement->fresh()->faculty_review_status)->toBe('approved')
        ->and($placement->fresh()->document_status)->toBe('approved');

    $this->actingAs($student)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('name="activities"', false);
});

test('an administrator edit sends an approved placement back through faculty review', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create([
        'role' => 'student',
        'course' => 'BACHELOR OF COMMERCE',
        'verification_status' => 'approved',
    ]);
    $placement = Placement::create([
        'student_id' => $student->id,
        'faculty_name' => 'Faculty of Commerce',
        'faculty_review_status' => 'approved',
        'company_name' => 'Northstar Engineering',
        'start_date' => today()->addDays(2)->toDateString(),
        'end_date' => today()->addDays(32)->toDateString(),
        'status' => 'active',
        'document_status' => 'approved',
        'student_confirmed_at' => now(),
    ]);
    $placement->schedules()->create([
        'day_of_week' => 1,
        'start_time' => '08:00',
        'end_time' => '17:00',
    ]);

    $this->actingAs($admin)->put(route('admin.placements.update', $placement), [
        'company_name' => 'Updated Engineering',
        'company_address' => 'New Road',
        'start_date' => today()->addDays(3)->toDateString(),
        'end_date' => today()->addDays(33)->toDateString(),
        'workdays' => ['1', '2'],
        'start_time' => '09:00',
        'end_time' => '17:00',
    ])->assertRedirect();

    expect($placement->fresh()->faculty_review_status)->toBe('submitted')
        ->and($placement->fresh()->document_status)->toBe('pending')
        ->and($placement->fresh()->student_confirmed_at)->toBeNull()
        ->and($placement->fresh()->schedules)->toHaveCount(2);
});