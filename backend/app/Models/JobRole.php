<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobRole extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'job_category_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
