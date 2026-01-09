<?php

namespace App\Jobs;

use App\Models\ScrapedData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExportScrapedDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $filePath,
        public ?int $limit = null,
        public array $filters = []
    ) {
        $this->onQueue(config('scraping.queue_name', 'scraping'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $query = ScrapedData::processed();

            // Apply filters
            if (!empty($this->filters['from_date'])) {
                $query->where('scraped_at', '>=', $this->filters['from_date']);
            }

            if (!empty($this->filters['to_date'])) {
                $query->where('scraped_at', '<=', $this->filters['to_date']);
            }

            // Apply limit
            if ($this->limit) {
                $query->limit($this->limit);
            }

            // Open file
            $file = fopen($this->filePath, 'w');
            
            if (!$file) {
                throw new \Exception("Cannot open file: {$this->filePath}");
            }

            // Write header
            $headers = $this->getHeaders();
            fputcsv($file, $headers);

            // Write data in chunks
            $count = 0;
            $query->chunk(1000, function ($records) use ($file, &$count) {
                foreach ($records as $record) {
                    $row = $this->formatRow($record);
                    fputcsv($file, $row);
                    
                    // Mark as exported
                    $record->markAsExported();
                    $count++;
                }
            });

            fclose($file);

            Log::info("Exported {$count} records to {$this->filePath}");

        } catch (\Exception $e) {
            Log::error("Export job failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Get CSV headers.
     */
    protected function getHeaders(): array
    {
        return [
            'ID',
            'URL',
            'Title',
            'Description',
            'Price',
            'Category',
            'Author',
            'Date',
            'Images',
            'Scraped At',
        ];
    }

    /**
     * Format a record as a CSV row.
     */
    protected function formatRow(ScrapedData $record): array
    {
        $data = $record->data;

        return [
            $record->id,
            $record->source_url,
            $data['title'] ?? '',
            $data['description'] ?? '',
            $data['price'] ?? '',
            $data['category'] ?? '',
            $data['author'] ?? '',
            $data['date'] ?? '',
            implode('|', $data['images'] ?? []),
            $record->scraped_at->toDateTimeString(),
        ];
    }
}
