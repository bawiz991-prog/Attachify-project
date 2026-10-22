<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

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
        'student_number',
        'registration_number',
        'course',
        'password',
    ];

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class, 'student_id');
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
        ];
    }
}
