<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('faculty')->nullable();
            $table->foreignId('coordinator_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $programs = [
            'BACHELOR OF TRAVEL AND TOURISM MANAGEMENT',
            'BACHELOR OF INTERNATIONAL BUSINESS',
            'BACHELOR OF BUSINESS COMPUTING',
            'BACHELOR OF BUSINESS STATISTICS',
            'BACHELOR OF CATERING AND HOTEL MANAGEMENT',
            'BACHELOR OF COMMERCE',
            'BACHELOR OF ENTREPRENEURSHIP',
            'BACHELOR OF HUMAN RESOURCE MANAGEMENT',
            'BACHELOR OF LEADERSHIP AND GOVERNANCE',
            'BACHELOR OF TRANSPORT AND LOGISTICS MANAGEMENT',
            'BACHELOR OF LEISURE AND HOSPITALITY MANAGEMENT',
            'BACHELOR OF OFFICE AND INFORMATION MANAGEMENT',
            'BACHELOR OF PROCUREMENT AND SUPPLY CHAIN MANAGEMENT',
            'BACHELOR OF REAL ESTATE MANAGEMENT',
            'BACHELOR OF SCIENCE IN ACCOUNTING',
            'BACHELOR OF SCIENCE IN FINANCE',
            'BACHELOR OF SCIENCE IN MARKETING',
            'BACHELOR OF ARTS IN ECONOMICS',
            'BACHELOR OF BUSINESS ADMINISTRATION',
            'BACHELOR OF LEISURE EVENTS & HOTEL MANAGEMENT',
        ];

        $now = now();
        DB::table('programs')->insert(array_map(
            fn (string $name) => ['name' => $name, 'created_at' => $now, 'updated_at' => $now],
            $programs,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
