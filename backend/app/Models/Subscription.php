<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // <--- 1. Add this import

class Subscription extends Model
{
    use HasUuids; // <--- 2. Add this line inside the class

    // Rest of your model content...
}
