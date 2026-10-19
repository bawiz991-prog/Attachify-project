<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlacementSchedule extends Model
{
    protected $fillable = [
        'placement_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }
}