<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CandidateProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'headline',
        'summary',
        'city',
        'region',
        'country',
        'date_of_birth',
        'gender',
        'photo_path',
        'years_experience',
        'current_job_title',
        'current_company',
        'preferred_job_title',
        'preferred_location',
        'remote_preference',
        'employment_type_preference',
        'expected_salary_monthly_etb',
        'availability',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'years_experience' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(CandidateEducation::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(CandidateExperience::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(CandidateSkill::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(CandidateProject::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(CandidateCertification::class);
    }

    public function languages(): HasMany
    {
        return $this->hasMany(CandidateLanguage::class);
    }

    public function preference(): HasOne
    {
        return $this->hasOne(CandidatePreference::class);
    }

    public function cvVersions(): HasMany
    {
        return $this->hasMany(CvVersion::class);
    }
}
