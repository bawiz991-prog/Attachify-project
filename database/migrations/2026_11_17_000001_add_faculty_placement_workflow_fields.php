<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('faculty')->nullable()->index();
        });

        Schema::table('placements', function (Blueprint $table) {
            $table->string('faculty_name')->nullable()->index();
            $table->foreignId('faculty_handler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('faculty_review_status')->nullable()->index();
            $table->text('faculty_verification_request')->nullable();
            $table->text('student_verification_response')->nullable();
        });

        $programFaculties = [
            'Faculty of Tourism, Hospitality & Languages' => [
                'BACHELOR OF TRAVEL AND TOURISM MANAGEMENT',
                'BACHELOR OF CATERING AND HOTEL MANAGEMENT',
                'BACHELOR OF LEISURE AND HOSPITALITY MANAGEMENT',
                'BACHELOR OF LEISURE EVENTS & HOTEL MANAGEMENT',
            ],
            'Faculty of Marketing and International Business' => [
                'BACHELOR OF INTERNATIONAL BUSINESS',
                'BACHELOR OF SCIENCE IN MARKETING',
            ],
            'Faculty of Procurement & Logistics Management' => [
                'BACHELOR OF TRANSPORT AND LOGISTICS MANAGEMENT',
                'BACHELOR OF PROCUREMENT AND SUPPLY CHAIN MANAGEMENT',
            ],
            'Faculty of Economics Energy & Management Science' => [
                'BACHELOR OF ARTS IN ECONOMICS',
                'BACHELOR OF BUSINESS STATISTICS',
            ],
            'Faculty of Computing and Informatics' => [
                'BACHELOR OF BUSINESS COMPUTING',
                'BACHELOR OF OFFICE AND INFORMATION MANAGEMENT',
            ],
            'Faculty of Entrepreneurship and Small Business Management' => [
                'BACHELOR OF ENTREPRENEURSHIP',
            ],
            'Faculty of Business Administration' => [
                'BACHELOR OF BUSINESS ADMINISTRATION',
            ],
            'Faculty of Management' => [
                'BACHELOR OF HUMAN RESOURCE MANAGEMENT',
                'BACHELOR OF LEADERSHIP AND GOVERNANCE',
            ],
            'Faculty of Commerce' => [
                'BACHELOR OF COMMERCE',
                'BACHELOR OF REAL ESTATE MANAGEMENT',
                'BACHELOR OF SCIENCE IN ACCOUNTING',
                'BACHELOR OF SCIENCE IN FINANCE',
            ],
        ];

        foreach ($programFaculties as $faculty => $programs) {
            \Illuminate\Support\Facades\DB::table('programs')->whereIn('name', $programs)->update(['faculty' => $faculty]);
        }
    }

    public function down(): void
    {
        Schema::table('placements', function (Blueprint $table) {
            $table->dropForeign(['faculty_handler_id']);
            $table->dropIndex(['faculty_name']);
            $table->dropIndex(['faculty_review_status']);
            $table->dropColumn([
                'faculty_name',
                'faculty_handler_id',
                'faculty_review_status',
                'faculty_verification_request',
                'student_verification_response',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['faculty']);
            $table->dropColumn('faculty');
        });
    }
};