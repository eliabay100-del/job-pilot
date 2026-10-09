<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Job extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'job_source_id',
        'created_by',
        'title',
        'slug',
        'description',
        'responsibilities',
        'requirements',
        'qualifications',
        'benefits',
        'city',
        'region',
        'country',
        'work_mode',
        'employment_type',
        'seniority',
        'job_category_id',
        'job_role_id',
        'industry_id',
        'min_education_level_id',
        'experience_years_min',
        'experience_years_max',
        'salary_min_monthly',
        'salary_max_monthly',
        'salary_currency',
        'salary_negotiable',
        'application_deadline',
        'status',
        'is_sponsored',
        'is_featured',
        'published_at',
        'expires_at',
        'source_url',
        'first_seen_at',
        'last_checked_at',
        'content_fingerprint',
        'risk_score',
        'risk_level',
    ];

    protected function casts(): array
    {
        return [
            'experience_years_min' => 'integer',
            'experience_years_max' => 'integer',
            'salary_min_monthly' => 'integer',
            'salary_max_monthly' => 'integer',
            'salary_negotiable' => 'boolean',
            'is_sponsored' => 'boolean',
            'is_featured' => 'boolean',
            'application_deadline' => 'date',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'risk_score' => 'integer',
            'views_count' => 'integer',
        ];
    }

    /**
     * Searchable and listable jobs only. Drafts and moderation states stay
     * invisible to everyone except their author (see JobPolicy).
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function jobSource(): BelongsTo
    {
        return $this->belongsTo(JobSource::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function jobCategory(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class);
    }

    public function jobRole(): BelongsTo
    {
        return $this->belongsTo(JobRole::class);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function minEducationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class, 'min_education_level_id');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'job_skills')
            ->withPivot(['is_required', 'min_years'])
            ->withTimestamps();
    }

    public function sourceRecords(): HasMany
    {
        return $this->hasMany(JobSourceRecord::class);
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_jobs')->withTimestamps();
    }

    public function matches(): HasMany
    {
        return $this->hasMany(JobMatch::class);
    }
}
