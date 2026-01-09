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
            'Título',
            'Descripción',
            'Total Headings',
            'Total Links',
            'Total Imágenes',
            'Palabras',
            'Headings (Top 3)',
            'Links (Top 5)',
            'Imágenes (URLs)',
            'Fecha de Extracción',
        ];
    }

    /**
     * Format a record as a CSV row.
     */
    protected function formatRow(ScrapedData $record): array
    {
        $data = $record->data;

        // Extract top headings text
        $topHeadings = [];
        if (!empty($data['headings'])) {
            foreach (array_slice($data['headings'], 0, 3) as $heading) {
                $topHeadings[] = $heading['text'] ?? '';
            }
        }

        // Extract top links
        $topLinks = [];
        if (!empty($data['links'])) {
            foreach (array_slice($data['links'], 0, 5) as $link) {
                $topLinks[] = ($link['text'] ?? '') . ' (' . ($link['url'] ?? '') . ')';
            }
        }

        return [
            $record->id,
            $record->source_url,
            $data['title'] ?? '',
            $data['description'] ?? '',
            $data['headings_count'] ?? 0,
            $data['links_count'] ?? 0,
            $data['images_count'] ?? 0,
            $data['metadata']['word_count'] ?? 0,
            implode('|', $topHeadings),
            implode('|', $topLinks),
            implode('|', $data['images'] ?? []),
            $record->scraped_at->toDateTimeString(),
        ];
    }
}
