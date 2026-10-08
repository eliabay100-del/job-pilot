<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillAlias extends Model
{
    use HasFactory;

    protected $fillable = ['skill_id', 'alias', 'locale'];

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
