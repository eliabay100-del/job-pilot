<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobMatch extends Model
{
    use HasFactory;

    protected $table = 'job_matches';

    protected $fillable = [
        'candidate_profile_id',
        'job_id',
        'overall_score',
        'component_scores',
        'matched_skills',
        'missing_skills',
        'weak_areas',
        'hard_requirement_flags',
        'recommendation',
        'model_version',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'overall_score' => 'decimal:2',
            'component_scores' => 'array',
            'matched_skills' => 'array',
            'missing_skills' => 'array',
            'weak_areas' => 'array',
            'hard_requirement_flags' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
