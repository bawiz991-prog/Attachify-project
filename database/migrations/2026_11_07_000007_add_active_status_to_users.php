<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });

        DB::table('supervisor_meetings')
            ->where('mode', 'teams')
            ->update(['mode' => 'google_meet']);
    }

    public function down(): void
    {
        DB::table('supervisor_meetings')
            ->where('mode', 'google_meet')
            ->update(['mode' => 'teams']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
