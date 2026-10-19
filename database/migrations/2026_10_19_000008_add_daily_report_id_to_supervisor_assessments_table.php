<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisor_assessments', function (Blueprint $table) {
            $table->foreignId('daily_report_id')->nullable()->after('placement_id')->constrained()->cascadeOnDelete();
            $table->unique(['daily_report_id', 'supervisor_id'], 'sup_assessments_report_supervisor_unique');
        });
    }

    public function down(): void
    {
        Schema::table('supervisor_assessments', function (Blueprint $table) {
            $table->dropUnique('sup_assessments_report_supervisor_unique');
            $table->dropForeign(['daily_report_id']);
            $table->dropColumn('daily_report_id');
        });
    }
};