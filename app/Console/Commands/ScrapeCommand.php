<?php

namespace App\Console\Commands;

use App\Jobs\BatchScrapeJob;
use App\Jobs\ScrapeUrlJob;
use App\Services\Scrapers\ExampleScraper;
use Illuminate\Console\Command;

class ScrapeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'scrape:run
                            {--url= : Single URL to scrape}
                            {--file= : File containing URLs (one per line)}
                            {--scraper=App\Services\Scrapers\ExampleScraper : Scraper class to use}
                            {--async : Run asynchronously using queues}
                            {--batch-size=100 : Number of URLs per batch}';

    /**
     * The console command description.
     */
    protected $description = 'Run web scraping tasks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = $this->option('url');
        $file = $this->option('file');
        $scraperClass = $this->option('scraper');
        $async = $this->option('async');

        // Validate scraper class
        if (!class_exists($scraperClass)) {
            $this->error("Scraper class not found: {$scraperClass}");
            return 1;
        }

        // Get URLs
        $urls = [];
        
        if ($url) {
            $urls[] = $url;
        } elseif ($file) {
            if (!file_exists($file)) {
                $this->error("File not found: {$file}");
                return 1;
            }
            
            $urls = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        } else {
            $this->error('Please provide either --url or --file option');
            return 1;
        }

        $urls = array_filter(array_map('trim', $urls));

        if (empty($urls)) {
            $this->error('No URLs to scrape');
            return 1;
        }

        $this->info('Starting scraping for ' . count($urls) . ' URLs...');

        if ($async) {
            return $this->runAsync($urls, $scraperClass);
        } else {
            return $this->runSync($urls, $scraperClass);
        }
    }

    /**
     * Run scraping synchronously.
     */
    protected function runSync(array $urls, string $scraperClass): int
    {
        $scraper = app($scraperClass);
        $bar = $this->output->createProgressBar(count($urls));
        
        $success = 0;
        $failed = 0;

        foreach ($urls as $url) {
            try {
                $data = $scraper->scrape($url);
                
                if ($data) {
                    $success++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("\nError scraping {$url}: {$e->getMessage()}");
                $failed++;
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Completed: {$success} successful, {$failed} failed");

        return 0;
    }

    /**
     * Run scraping asynchronously using queues.
     */
    protected function runAsync(array $urls, string $scraperClass): int
    {
        $this->info('Dispatching ' . count($urls) . ' jobs to queue...');

        foreach ($urls as $url) {
            ScrapeUrlJob::dispatch($url, $scraperClass);
        }

        $this->info('Jobs dispatched successfully. Monitor with: php artisan queue:work --queue=scraping');

        return 0;
    }
}
