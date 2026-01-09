<?php

namespace App\Jobs;

use App\Services\Scrapers\BaseScraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeUrlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [60, 120, 300]; // Backoff in seconds

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $url,
        public string $scraperClass,
        public array $options = []
    ) {
        $this->onQueue(config('scraping.queue_name', 'scraping'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Instantiate the scraper
            $scraper = app($this->scraperClass);

            if (!$scraper instanceof BaseScraper) {
                throw new \Exception("Invalid scraper class: {$this->scraperClass}");
            }

            // Scrape the URL
            $data = $scraper->scrape($this->url);

            if (!$data) {
                Log::warning("No data extracted from {$this->url}");
            }

        } catch (\Exception $e) {
            Log::error("Scraping job failed for {$this->url}: {$e->getMessage()}");
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Scraping job permanently failed for {$this->url}: {$exception->getMessage()}");
    }
}
