<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyReport extends Model
{
    protected $fillable = [
        'placement_id',
        'report_date',
        'activities',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DailyReportComment::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(SupervisorAssessment::class);
    }
}