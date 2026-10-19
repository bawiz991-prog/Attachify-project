<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supervisor_assessments')) {
            Schema::create('supervisor_assessments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('placement_id')->constrained()->cascadeOnDelete();
                $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->text('strengths');
                $table->text('areas_for_improvement')->nullable();
                $table->text('overall_feedback');
                $table->timestamp('submitted_at');
                $table->timestamps();
                $table->index(['placement_id', 'supervisor_id', 'submitted_at'], 'sup_assessments_placement_supervisor_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_assessments');
    }
};