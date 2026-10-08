<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSourceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_source_id',
        'external_id',
        'source_url',
        'raw_payload',
        'status',
        'job_id',
        'error',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function jobSource(): BelongsTo
    {
        return $this->belongsTo(JobSource::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
