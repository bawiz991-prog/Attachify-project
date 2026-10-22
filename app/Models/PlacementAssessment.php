<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlacementAssessment extends Model
{
    protected $fillable = [
        'placement_id',
        'supervisor_id',
        'supervisor_role',
        'overall_score',
        'physical_score',
        'teams_score',
        'feedback',
        'faculty_report_path',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'overall_score' => 'decimal:2',
            'physical_score' => 'decimal:2',
            'teams_score' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
