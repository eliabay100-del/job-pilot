<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'base_url',
        'is_active',
    ];

    // Mirrors the column default so a firstOrCreate'd source is usable without
    // a refresh: Postgres fills the default but the in-memory model does not.
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(JobSourceRecord::class);
    }
}
