<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('scheduled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode')->default('teams');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->text('teams_url')->nullable();
            $table->text('location')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('scheduled')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_meetings');
    }
};