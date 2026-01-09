<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proxy extends Model
{
    use HasFactory;

    protected $fillable = [
        'host',
        'port',
        'username',
        'password',
        'protocol',
        'status',
        'success_count',
        'failure_count',
        'success_rate',
        'last_used_at',
        'last_success_at',
        'last_failure_at',
        'consecutive_failures',
        'response_time_avg',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
        'success_rate' => 'decimal:2',
    ];

    /**
     * Get the scraping logs for this proxy.
     */
    public function scrapingLogs(): HasMany
    {
        return $this->hasMany(ScrapingLog::class);
    }

    /**
     * Get the proxy URL string.
     */
    public function getProxyUrl(): string
    {
        $auth = $this->username && $this->password
            ? "{$this->username}:{$this->password}@"
            : '';

        return "{$this->protocol}://{$auth}{$this->host}:{$this->port}";
    }

    /**
     * Record a successful request.
     */
    public function recordSuccess(int $responseTime): void
    {
        $this->success_count++;
        $this->consecutive_failures = 0;
        $this->last_success_at = now();
        $this->last_used_at = now();
        
        // Update average response time
        if ($this->success_count === 1) {
            $this->response_time_avg = $responseTime;
        } else {
            $this->response_time_avg = (int) (
                ($this->response_time_avg * ($this->success_count - 1) + $responseTime) 
                / $this->success_count
            );
        }

        $this->updateSuccessRate();
        $this->updateStatus();
        $this->save();
    }

    /**
     * Record a failed request.
     */
    public function recordFailure(): void
    {
        $this->failure_count++;
        $this->consecutive_failures++;
        $this->last_failure_at = now();
        $this->last_used_at = now();
        
        $this->updateSuccessRate();
        $this->updateStatus();
        $this->save();
    }

    /**
     * Update the success rate.
     */
    protected function updateSuccessRate(): void
    {
        $total = $this->success_count + $this->failure_count;
        
        if ($total > 0) {
            $this->success_rate = ($this->success_count / $total) * 100;
        }
    }

    /**
     * Update the proxy status based on performance.
     */
    protected function updateStatus(): void
    {
        // Ban if too many consecutive failures
        if ($this->consecutive_failures >= config('scraping.proxy.max_consecutive_failures', 5)) {
            $this->status = 'banned';
            return;
        }

        // Set to active if success rate is good
        if ($this->success_rate >= config('scraping.proxy.min_success_rate', 70) && $total = $this->success_count + $this->failure_count >= 10) {
            $this->status = 'active';
            return;
        }

        // Set to inactive if success rate is poor
        if ($this->success_rate < config('scraping.proxy.min_success_rate', 70) && $total >= 10) {
            $this->status = 'inactive';
            return;
        }
    }

    /**
     * Scope to get active proxies.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get best performing proxies.
     */
    public function scopeBestPerforming($query, int $limit = 10)
    {
        return $query->active()
            ->orderByDesc('success_rate')
            ->orderBy('response_time_avg')
            ->limit($limit);
    }
}
