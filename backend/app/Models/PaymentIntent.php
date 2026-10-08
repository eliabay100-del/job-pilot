<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // <--- 1. Add this import string

class PaymentIntent extends Model
{
    use HasUuids; // <--- 2. Add this line inside your class statement

    // Your existing fillable, casts, or relationship properties...
}
