<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScrapedData extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_url',
        'unique_identifier',
        'data',
        'status',
        'scraped_at',
        'exported_at',
    ];

    protected $casts = [
        'data' => 'array',
        'scraped_at' => 'datetime',
        'exported_at' => 'datetime',
    ];

    /**
     * Scope to get pending data.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get processed data.
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    /**
     * Mark as exported.
     */
    public function markAsExported(): void
    {
        $this->update([
            'status' => 'exported',
            'exported_at' => now(),
        ]);
    }
}
