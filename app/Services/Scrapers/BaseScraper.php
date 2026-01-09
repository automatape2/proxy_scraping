<?php

namespace App\Services\Scrapers;

use App\Models\Proxy;
use App\Models\ScrapingLog;
use App\Services\ProxyManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

abstract class BaseScraper
{
    protected ProxyManager $proxyManager;
    protected int $maxRetries;
    protected int $retryDelay;
    protected int $timeout;
    protected bool $useProxy;
    protected ?Proxy $currentProxy = null;
    protected array $headers = [];

    public function __construct(ProxyManager $proxyManager)
    {
        $this->proxyManager = $proxyManager;
        $this->maxRetries = config('scraping.max_retries', 3);
        $this->retryDelay = config('scraping.retry_delay', 2);
        $this->timeout = config('scraping.timeout', 30);
        $this->useProxy = config('scraping.use_proxy', true);
        $this->setupHeaders();
    }

    /**
     * Setup default headers.
     */
    protected function setupHeaders(): void
    {
        $this->headers = [
            'User-Agent' => $this->getRandomUserAgent(),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
        ];
    }

    /**
     * Get a random user agent.
     */
    protected function getRandomUserAgent(): string
    {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];

        return $userAgents[array_rand($userAgents)];
    }

    /**
     * Fetch a URL with retry logic and proxy rotation.
     */
    protected function fetch(string $url, array $options = []): ?string
    {
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            $attempt++;
            $startTime = microtime(true);

            try {
                // Get a proxy if enabled
                if ($this->useProxy) {
                    $this->currentProxy = $this->proxyManager->getNextProxy();
                    
                    if (!$this->currentProxy) {
                        throw new \Exception('No available proxies');
                    }
                }

                // Build HTTP request
                $httpClient = Http::timeout($this->timeout)
                    ->withHeaders(array_merge($this->headers, $options['headers'] ?? []))
                    ->withOptions([
                        'verify' => $options['verify'] ?? false,
                        'allow_redirects' => $options['allow_redirects'] ?? true,
                    ]);

                // Add proxy if available
                if ($this->currentProxy) {
                    $httpClient->withOptions([
                        'proxy' => $this->currentProxy->getProxyUrl(),
                    ]);
                }

                // Make request
                $response = $httpClient->get($url);
                $responseTime = (int) ((microtime(true) - $startTime) * 1000);

                // Check if successful
                if ($response->successful()) {
                    $this->logSuccess($url, $response->status(), $responseTime);
                    
                    if ($this->currentProxy) {
                        $this->currentProxy->recordSuccess($responseTime);
                    }

                    return $response->body();
                }

                // Handle non-successful response
                $this->logFailure($url, $response->status(), 'HTTP error: ' . $response->status(), $attempt);
                
                if ($this->currentProxy) {
                    $this->currentProxy->recordFailure();
                }

            } catch (\Exception $e) {
                $this->logFailure($url, null, $e->getMessage(), $attempt);
                
                if ($this->currentProxy) {
                    $this->currentProxy->recordFailure();
                }

                Log::warning("Scraping attempt {$attempt} failed for {$url}: {$e->getMessage()}");
            }

            // Wait before retry
            if ($attempt < $this->maxRetries) {
                sleep($this->retryDelay * $attempt);
            }
        }

        return null;
    }

    /**
     * Parse HTML content using Symfony DomCrawler.
     */
    protected function parse(string $html): Crawler
    {
        return new Crawler($html);
    }

    /**
     * Log successful scraping.
     */
    protected function logSuccess(string $url, int $responseCode, int $responseTime): void
    {
        ScrapingLog::create([
            'url' => $url,
            'proxy_id' => $this->currentProxy?->id,
            'status' => 'success',
            'response_code' => $responseCode,
            'response_time' => $responseTime,
            'retry_count' => 0,
        ]);
    }

    /**
     * Log failed scraping.
     */
    protected function logFailure(string $url, ?int $responseCode, string $error, int $retryCount): void
    {
        ScrapingLog::create([
            'url' => $url,
            'proxy_id' => $this->currentProxy?->id,
            'status' => $retryCount < $this->maxRetries ? 'retry' : 'failed',
            'response_code' => $responseCode,
            'error_message' => $error,
            'retry_count' => $retryCount,
        ]);
    }

    /**
     * Extract data from a URL - must be implemented by child classes.
     */
    abstract public function scrape(string $url): ?array;

    /**
     * Process and clean extracted data.
     */
    abstract protected function processData(array $rawData): array;

    /**
     * Validate extracted data.
     */
    abstract protected function validateData(array $data): bool;
}
