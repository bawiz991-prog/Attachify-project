<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'role' => ['required', Rule::in(['student', 'field_supervisor', 'academic_supervisor'])],
            'course' => ['required_if:role,student', 'nullable', 'string', 'max:255'],
            'registration_number' => ['required_if:role,student', 'nullable', 'string', 'max:100', 'unique:users,registration_number'],
            'student_number' => ['required_if:role,student', 'nullable', 'string', 'max:100', 'unique:users,student_number'],
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        $user = new User([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'course' => $validated['course'] ?? null,
            'registration_number' => $validated['registration_number'] ?? null,
            'student_number' => $validated['student_number'] ?? null,
        ]);
        $user->role = $validated['role'];
        $user->verification_status = 'pending';
        $user->save();

        return $user;
    }
}
