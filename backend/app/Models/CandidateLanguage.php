<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateLanguage extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_profile_id',
        'language',
        'proficiency',
        'speaking_level',
        'listening_level',
        'reading_level',
        'writing_level',
    ];

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
