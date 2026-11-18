<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use MustVerifyEmailTrait;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'role',
        'faculty',
        'is_active',
        'student_number',
        'registration_number',
        'course',
        'password',
        'must_change_password',
        'verification_status',
        'verified_by_id',
        'verified_at',
        'verification_notes',
    ];

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class, 'student_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'course', 'name');
    }

    public function fieldSupervisions(): HasMany
    {
        return $this->hasMany(Placement::class, 'field_supervisor_id');
    }

    public function academicSupervisions(): HasMany
    {
        return $this->hasMany(Placement::class, 'academic_supervisor_id');
    }

    public function sentSupervisorMessages(): HasMany
    {
        return $this->hasMany(SupervisorMessage::class, 'sender_id');
    }

    public function placementAssessments(): HasMany
    {
        return $this->hasMany(PlacementAssessment::class, 'supervisor_id');
    }

    public function reviewedUsers(): HasMany
    {
        return $this->hasMany(self::class, 'verified_by_id');
    }

    public function isApproved(): bool
    {
        return $this->verification_status === 'approved';
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
