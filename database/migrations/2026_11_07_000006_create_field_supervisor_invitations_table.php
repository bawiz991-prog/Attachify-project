<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('placements', function (Blueprint $table) {
            $table->string('field_supervisor_email')->nullable();
        });

        Schema::create('field_supervisor_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['placement_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_supervisor_invitations');

        Schema::table('placements', function (Blueprint $table) {
            $table->dropColumn('field_supervisor_email');
        });
    }
};
