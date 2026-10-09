<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsage extends Model
{
    use HasFactory;

    protected $table = 'ai_usage';

    protected $fillable = [
        'user_id', 'operation', 'provider', 'model', 'prompt_version',
        'input_tokens', 'output_tokens', 'estimated_cost_usd',
        'credits_consumed', 'success', 'error_code', 'latency_ms', 'context_refs',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost_usd' => 'decimal:6',
            'success' => 'boolean',
            'context_refs' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}