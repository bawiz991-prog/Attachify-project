<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('programs')
            ->whereNotNull('faculty')
            ->orderBy('id')
            ->get(['name', 'faculty'])
            ->each(function (object $program): void {
                $studentIds = DB::table('users')
                    ->where('course', $program->name)
                    ->select('id');

                DB::table('placements')
                    ->whereNull('faculty_name')
                    ->whereIn('student_id', $studentIds)
                    ->update(['faculty_name' => $program->faculty]);
            });
    }

    public function down(): void
    {
        // Faculty values are part of placement history and remain after rollback.
    }
};