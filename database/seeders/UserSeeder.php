xam<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        foreach ([
            ['name' => 'Benjamin Kasasa', 'student_number' => '24007001', 'course' => 'BACHELOR OF COMMERCE'],
            ['name' => 'Nansubuga Lairah', 'student_number' => '24007002', 'course' => 'BACHELOR OF HUMAN RESOURCE MANAGEMENT'],
            ['name' => 'Patience Vitoria', 'student_number' => '24007003', 'course' => 'BACHELOR OF BUSINESS COMPUTING'],
            ['name' => 'Elijah Wampamba', 'student_number' => '24007004', 'course' => 'BACHELOR OF SCIENCE IN MARKETING'],
            ['name' => 'Alicia Karren', 'student_number' => '24007005', 'course' => 'BACHELOR OF TRAVEL AND TOURISM MANAGEMENT'],
        ] as $student) {
            $email = strtolower($student['student_number']).'@mubs.ac.ug';

            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $student['name'],
                    'role' => 'student',
                    'course' => $student['course'],
                    'student_number' => $student['student_number'],
                    'registration_number' => 'DEMO-'.substr($student['student_number'], -4),
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'must_change_password' => true,
                    'verification_status' => 'approved',
                    'verified_at' => now(),
                ]
            );
        }

        foreach (config('faculties') as $faculty) {
            $facultySlug = Str::slug($faculty);

            foreach ([1, 2, 3] as $handlerNumber) {
                User::query()->updateOrCreate(
                    ['email' => "demo-handler-{$handlerNumber}-{$facultySlug}@demo.mubs.ac.ug"],
                    [
                        'name' => "Demo Handler {$handlerNumber} - {$faculty}",
                        'role' => 'faculty_handler',
                        'faculty' => $faculty,
                        'password' => 'DemoPass123!',
                        'email_verified_at' => now(),
                        'is_active' => true,
                        'must_change_password' => false,
                        'verification_status' => 'approved',
                        'verified_at' => now(),
                    ]
                );
            }

            User::query()->updateOrCreate(
                ['email' => "demo-dean-{$facultySlug}@demo.mubs.ac.ug"],
                [
                    'name' => "Demo Dean - {$faculty}",
                    'role' => 'faculty_dean',
                    'faculty' => $faculty,
                    'password' => 'DemoPass123!',
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'must_change_password' => false,
                    'verification_status' => 'approved',
                    'verified_at' => now(),
                ]
            );
        }
    }
}
