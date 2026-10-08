<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // <--- 1. Add this import line

class Notification extends Model
{
    use HasUuids; // <--- 2. Add this use trait declaration line

    // Keep all your existing fillable, guarded, or casts properties exactly as they are...
}
