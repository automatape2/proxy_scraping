<?php

namespace App\Services;

use App\Models\Proxy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ProxyManager
{
    protected const CACHE_KEY = 'available_proxies';
    protected const CACHE_TTL = 300; // 5 minutes

    /**
     * Get the next available proxy.
     */
    public function getNextProxy(): ?Proxy
    {
        $proxies = $this->getAvailableProxies();

        if ($proxies->isEmpty()) {
            return null;
        }

        // Use weighted random selection based on success rate
        return $this->selectProxyByWeight($proxies);
    }

    /**
     * Get all available proxies.
     */
    protected function getAvailableProxies(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Proxy::active()
                ->where('success_rate', '>=', config('scraping.proxy.min_success_rate', 70))
                ->orderByDesc('success_rate')
                ->orderBy('response_time_avg')
                ->get();
        });
    }

    /**
     * Select a proxy using weighted random selection.
     */
    protected function selectProxyByWeight(Collection $proxies): Proxy
    {
        // Create weights based on success rate and response time
        $weights = $proxies->map(function ($proxy) {
            // Higher success rate and lower response time = higher weight
            $timeWeight = $proxy->response_time_avg > 0 
                ? 1000 / $proxy->response_time_avg 
                : 1;
            
            return $proxy->success_rate * $timeWeight;
        });

        $totalWeight = $weights->sum();
        $random = mt_rand(0, (int) ($totalWeight * 100)) / 100;

        $currentWeight = 0;
        foreach ($proxies as $index => $proxy) {
            $currentWeight += $weights[$index];
            if ($random <= $currentWeight) {
                return $proxy;
            }
        }

        return $proxies->first();
    }

    /**
     * Test a proxy's connectivity.
     */
    public function testProxy(Proxy $proxy, string $testUrl = 'https://www.google.com'): bool
    {
        $startTime = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withOptions([
                    'proxy' => $proxy->getProxyUrl(),
                    'verify' => false,
                ])
                ->get($testUrl);

            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $proxy->recordSuccess($responseTime);
                return true;
            }

            $proxy->recordFailure();
            return false;
        } catch (\Exception $e) {
            $proxy->recordFailure();
            return false;
        }
    }

    /**
     * Add a new proxy.
     */
    public function addProxy(
        string $host,
        int $port,
        string $protocol = 'http',
        ?string $username = null,
        ?string $password = null
    ): Proxy {
        $proxy = Proxy::create([
            'host' => $host,
            'port' => $port,
            'protocol' => $protocol,
            'username' => $username,
            'password' => $password,
            'status' => 'testing',
        ]);

        // Test the proxy immediately
        $this->testProxy($proxy);

        // Clear cache
        Cache::forget(self::CACHE_KEY);

        return $proxy;
    }

    /**
     * Import proxies from a list.
     */
    public function importProxies(array $proxyList): int
    {
        $count = 0;

        foreach ($proxyList as $proxyData) {
            try {
                $this->addProxy(
                    $proxyData['host'],
                    $proxyData['port'],
                    $proxyData['protocol'] ?? 'http',
                    $proxyData['username'] ?? null,
                    $proxyData['password'] ?? null
                );
                $count++;
            } catch (\Exception $e) {
                // Skip invalid proxies
                continue;
            }
        }

        return $count;
    }

    /**
     * Clean up banned and inactive proxies.
     */
    public function cleanup(int $daysInactive = 7): int
    {
        $cutoffDate = now()->subDays($daysInactive);

        return Proxy::where('status', 'banned')
            ->orWhere(function ($query) use ($cutoffDate) {
                $query->where('status', 'inactive')
                    ->where('last_used_at', '<', $cutoffDate);
            })
            ->delete();
    }

    /**
     * Refresh proxy statistics.
     */
    public function refreshStatistics(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get proxy statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total' => Proxy::count(),
            'active' => Proxy::where('status', 'active')->count(),
            'inactive' => Proxy::where('status', 'inactive')->count(),
            'banned' => Proxy::where('status', 'banned')->count(),
            'testing' => Proxy::where('status', 'testing')->count(),
            'avg_success_rate' => Proxy::active()->avg('success_rate'),
            'avg_response_time' => Proxy::active()->avg('response_time_avg'),
        ];
    }
}
