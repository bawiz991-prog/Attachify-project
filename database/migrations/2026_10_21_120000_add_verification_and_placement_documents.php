<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_number')->nullable()->unique();
            $table->string('course')->nullable();
            $table->string('verification_status')->default('approved')->index();
            $table->foreignId('verified_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
        });

        Schema::table('placements', function (Blueprint $table) {
            $table->string('placement_document_path')->nullable();
            $table->string('document_status')->default('approved')->index();
            $table->foreignId('document_reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('document_reviewed_at')->nullable();
            $table->text('document_review_notes')->nullable();
        });

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->text('challenges')->nullable();
            $table->text('skills_acquired')->nullable();
            $table->text('lessons_learned')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropColumn(['challenges', 'skills_acquired', 'lessons_learned']);
        });

        Schema::table('placements', function (Blueprint $table) {
            $table->dropForeign(['document_reviewed_by_id']);
            $table->dropIndex(['document_status']);
            $table->dropColumn([
                'placement_document_path',
                'document_status',
                'document_reviewed_by_id',
                'document_reviewed_at',
                'document_review_notes',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['verified_by_id']);
            $table->dropUnique(['registration_number']);
            $table->dropIndex(['verification_status']);
            $table->dropColumn([
                'registration_number',
                'course',
                'verification_status',
                'verified_by_id',
                'verified_at',
                'verification_notes',
            ]);
        });
    }
};
