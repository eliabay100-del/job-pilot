<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidatePreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_profile_id',
        'work_modes',
        'locations',
        'employment_types',
        'industries',
        'desired_job_role_ids',
        'desired_industry_ids',
        'job_category_ids',
        'salary_min_monthly_eth',
        'experience_level',
        'open_to_remote',
        'willing_to_relocate',
    ];

    protected function casts(): array
    {
        return [
            'work_modes' => 'array',
            'locations' => 'array',
            'employment_types' => 'array',
            'industries' => 'array',
            'desired_job_role_ids' => 'array',
            'desired_industry_ids' => 'array',
            'job_category_ids' => 'array',
            'open_to_remote' => 'boolean',
            'willing_to_relocate' => 'boolean',
        ];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
