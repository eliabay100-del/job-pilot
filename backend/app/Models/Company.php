<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'website',
        'logo_path',
        'industry_id',
        'city',
        'region',
        'country',
        'size',
        'type',
        'verification_status',
        'verified_at',
        'social_links',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'social_links' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_users')
            ->withPivot(['membership_role', 'status'])
            ->withTimestamps();
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(CompanyVerification::class);
    }
}
