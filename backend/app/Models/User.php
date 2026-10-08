<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'locale',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function candidateProfile(): HasOne
    {
        return $this->hasOne(CandidateProfile::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    public function ensureCandidateProfile(): CandidateProfile
    {
        // Do not use `$this->candidateProfile ?? create()`: accessing a HasOne
        // that has no row yet caches null on this instance, so every later call
        // re-inserts and hits the candidate_profiles_user_id_unique constraint.
        $profile = $this->relationLoaded('candidateProfile')
            ? $this->getRelation('candidateProfile')
            : null;

        if ($profile === null) {
            $profile = CandidateProfile::firstOrCreate(['user_id' => $this->id]);
            $this->setRelation('candidateProfile', $profile);
        }

        return $profile;
    }

    public function isJobSeeker(): bool
    {
        return $this->role === UserRole::JobSeeker;
    }
}
