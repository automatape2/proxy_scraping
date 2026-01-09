<?php

namespace App\Services;

use App\Models\ScrapedData;
use Illuminate\Support\Facades\Storage;

class CsvExporter
{
    protected string $delimiter = ',';
    protected string $enclosure = '"';
    protected string $escape = '\\';

    /**
     * Export data to CSV file.
     */
    public function export(
        string $filename,
        array $headers,
        callable $dataCallback,
        array $filters = []
    ): string {
        $filePath = storage_path("app/exports/{$filename}");
        
        // Ensure directory exists
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file = fopen($filePath, 'w');
        
        if (!$file) {
            throw new \Exception("Cannot create file: {$filePath}");
        }

        // Write UTF-8 BOM for Excel compatibility
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write headers
        fputcsv($file, $headers, $this->delimiter, $this->enclosure, $this->escape);

        // Write data
        $count = call_user_func($dataCallback, $file, $filters);

        fclose($file);

        return $filePath;
    }

    /**
     * Export scraped data to CSV.
     */
    public function exportScrapedData(array $filters = [], ?int $limit = null): string
    {
        $filename = 'scraped_data_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'ID',
            'URL',
            'Título',
            'Descripción',
            'Precio',
            'Categoría',
            'Autor',
            'Fecha',
            'Imágenes',
            'Fecha de Extracción',
        ];

        $filePath = $this->export(
            $filename,
            $headers,
            function ($file, $filters) use ($limit) {
                return $this->writeScrapedData($file, $filters, $limit);
            },
            $filters
        );

        return $filePath;
    }

    /**
     * Write scraped data to file.
     */
    protected function writeScrapedData($file, array $filters, ?int $limit): int
    {
        $query = ScrapedData::processed();

        // Apply filters
        if (!empty($filters['from_date'])) {
            $query->where('scraped_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('scraped_at', '<=', $filters['to_date']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply limit
        if ($limit) {
            $query->limit($limit);
        }

        $count = 0;

        // Process in chunks to handle large datasets
        $query->orderBy('scraped_at')->chunk(1000, function ($records) use ($file, &$count) {
            foreach ($records as $record) {
                $row = $this->formatScrapedDataRow($record);
                fputcsv($file, $row, $this->delimiter, $this->enclosure, $this->escape);
                
                // Mark as exported
                $record->markAsExported();
                $count++;
            }
        });

        return $count;
    }

    /**
     * Format scraped data record as CSV row.
     */
    protected function formatScrapedDataRow(ScrapedData $record): array
    {
        $data = $record->data;

        return [
            $record->id,
            $record->source_url,
            $data['title'] ?? '',
            $data['description'] ?? '',
            $this->formatPrice($data['price'] ?? null),
            $data['category'] ?? '',
            $data['author'] ?? '',
            $data['date'] ?? '',
            $this->formatArray($data['images'] ?? []),
            $record->scraped_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Format price for CSV.
     */
    protected function formatPrice($price): string
    {
        if ($price === null) {
            return '';
        }

        return number_format((float) $price, 2, '.', '');
    }

    /**
     * Format array for CSV (pipe-separated).
     */
    protected function formatArray(array $items): string
    {
        return implode('|', array_filter($items));
    }

    /**
     * Set CSV delimiter.
     */
    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * Get export statistics.
     */
    public function getExportStats(array $filters = []): array
    {
        $query = ScrapedData::query();

        if (!empty($filters['from_date'])) {
            $query->where('scraped_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('scraped_at', '<=', $filters['to_date']);
        }

        return [
            'total_records' => $query->count(),
            'processed' => (clone $query)->where('status', 'processed')->count(),
            'exported' => (clone $query)->where('status', 'exported')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'error' => (clone $query)->where('status', 'error')->count(),
        ];
    }
}
