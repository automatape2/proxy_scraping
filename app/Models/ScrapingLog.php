<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'url',
        'proxy_id',
        'status',
        'response_code',
        'response_time',
        'error_message',
        'retry_count',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the proxy that was used.
     */
    public function proxy(): BelongsTo
    {
        return $this->belongsTo(Proxy::class);
    }
}
