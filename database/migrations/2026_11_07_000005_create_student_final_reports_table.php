<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('placements', function (Blueprint $table) {
            $table->string('final_report_template_path')->nullable();
            $table->timestamp('report_template_sent_at')->nullable();
        });

        Schema::create('student_final_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('report_path');
            $table->string('status')->default('pending')->index();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reviewer_notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['placement_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_final_reports');

        Schema::table('placements', function (Blueprint $table) {
            $table->dropColumn(['final_report_template_path', 'report_template_sent_at']);
        });
    }
};
