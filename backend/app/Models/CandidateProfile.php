<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Candidate profile (one per job_seeker user). Minimal model for Phase 1 auth flow;
 * full fields/relationships are added with the candidate module.
 */
class CandidateProfile extends Model
{
    protected $fillable = ['user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
