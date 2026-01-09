<?php

namespace App\Jobs;

use App\Services\Scrapers\BaseScraper;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class BatchScrapeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $urls,
        public string $scraperClass,
        public string $batchName = 'Batch Scraping'
    ) {
        $this->onQueue(config('scraping.queue_name', 'scraping'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Create individual jobs for each URL
            $jobs = [];
            foreach ($this->urls as $url) {
                $jobs[] = new ScrapeUrlJob($url, $this->scraperClass);
            }

            // Dispatch as a batch
            $batch = Bus::batch($jobs)
                ->name($this->batchName)
                ->allowFailures()
                ->onQueue(config('scraping.queue_name', 'scraping'))
                ->then(function (Batch $batch) {
                    Log::info("Batch {$batch->id} completed successfully");
                })
                ->catch(function (Batch $batch, \Throwable $e) {
                    Log::error("Batch {$batch->id} encountered an error: {$e->getMessage()}");
                })
                ->finally(function (Batch $batch) {
                    Log::info("Batch {$batch->id} finished. Total: {$batch->totalJobs}, Failed: {$batch->failedJobs}");
                })
                ->dispatch();

            Log::info("Dispatched batch {$batch->id} with " . count($jobs) . " jobs");

        } catch (\Exception $e) {
            Log::error("Batch scrape job failed: {$e->getMessage()}");
            throw $e;
        }
    }
}
