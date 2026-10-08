<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateEducation extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_profile_id',
        'institution_id',
        'institution_name',
        'degree',
        'education_level_id',
        'field_of_study',
        'start_date',
        'end_date',
        'currently_studying',
        'start_year',
        'end_year',
        'cgpa',
        'grade',
        'notes',
        'description',
        'achievements',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'currently_studying' => 'boolean',
            'achievements' => 'array',
            'cgpa' => 'decimal:2',
        ];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }
}
