<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Placement extends Model
{
    protected $fillable = [
        'student_id',
        'faculty_name',
        'faculty_handler_id',
        'faculty_review_status',
        'faculty_verification_request',
        'student_verification_response',
        'field_supervisor_id',
        'field_supervisor_email',
        'academic_supervisor_id',
        'company_name',
        'company_address',
        'acceptance_document_path',
        'placement_document_path',
        'document_status',
        'document_reviewed_by_id',
        'document_reviewed_at',
        'document_review_notes',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'student_confirmed_at' => 'datetime',
            'field_supervisor_completed_at' => 'datetime',
            'report_template_sent_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function facultyHandler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'faculty_handler_id');
    }

    public function fieldSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_supervisor_id');
    }

    public function academicSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'academic_supervisor_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PlacementSchedule::class);
    }

    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(SupervisorMeeting::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(SupervisorAssessment::class);
    }

    public function finalAssessments(): HasMany
    {
        return $this->hasMany(PlacementAssessment::class);
    }

    public function studentFinalReports(): HasMany
    {
        return $this->hasMany(StudentFinalReport::class);
    }

    public function supervisorMessages(): HasMany
    {
        return $this->hasMany(SupervisorMessage::class);
    }
}
