<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // <--- 1. Add this namespace import

class PaymentTransaction extends Model
{
    use HasUuids; // <--- 2. Add this trait declaration inside the class

    // Keep any existing guarded, fillable, or billing relationship declarations intact...
}
