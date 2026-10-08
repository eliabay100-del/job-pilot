<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'city',
        'country',
        'website',
        'is_verified',
    ];

    protected function casts(): array
    {
        return ['is_verified' => 'boolean'];
    }
}
