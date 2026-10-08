<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_profile_id',
        'name',
        'description',
        'role',
        'url',
        'repository_url',
        'source',
        'tech_stack',
        'started_at',
        'completed_at',
        'achievements',
    ];

    protected function casts(): array
    {
        return [
            'tech_stack' => 'array',
            'started_at' => 'date',
            'completed_at' => 'date',
        ];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
