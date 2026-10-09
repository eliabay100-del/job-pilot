<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoverLetter extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_profile_id', 'job_id', 'source_cv_version_id', 'content', 'status',
        'provider', 'model', 'prompt_version', 'context_refs',
    ];

    protected function casts(): array
    {
        return ['context_refs' => 'array'];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function sourceCvVersion(): BelongsTo
    {
        return $this->belongsTo(CvVersion::class, 'source_cv_version_id');
    }
}