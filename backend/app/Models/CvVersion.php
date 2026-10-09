<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CvVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_profile_id',
        'document_id',
        'title',
        'kind',
        'tailored_for_job_id',
        'structured_data',
        'parse_status',
        'parse_confidence',
        'parse_error',
        'version_number',
    ];

    protected function casts(): array
    {
        return [
            'candidate_profile_id' => 'integer',
            'document_id' => 'integer',
            'version_number' => 'integer',
            'structured_data' => 'array',
            'parse_confidence' => 'decimal:2',
        ];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function tailoredForJob(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'tailored_for_job_id');
    }
}
