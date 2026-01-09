<?php

namespace App\Console\Commands;

use App\Services\CsvExporter;
use Illuminate\Console\Command;

class ExportDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'scrape:export
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--status=all : Data status (pending, processed, exported, error, all)}
                            {--limit= : Limit number of records}
                            {--async : Run asynchronously using queue}';

    /**
     * The console command description.
     */
    protected $description = 'Export scraped data to CSV';

    /**
     * Execute the console command.
     */
    public function handle(CsvExporter $exporter): int
    {
        // Build filters
        $filters = [];
        
        if ($from = $this->option('from')) {
            $filters['from_date'] = $from;
        }
        
        if ($to = $this->option('to')) {
            $filters['to_date'] = $to;
        }
        
        $status = $this->option('status');
        if ($status && $status !== 'all') {
            $filters['status'] = $status;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        // Show statistics
        $stats = $exporter->getExportStats($filters);
        
        $this->info('Export Statistics:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Records', $stats['total_records']],
                ['Processed', $stats['processed']],
                ['Exported', $stats['exported']],
                ['Pending', $stats['pending']],
                ['Error', $stats['error']],
            ]
        );

        if ($stats['total_records'] === 0) {
            $this->warn('No records to export');
            return 0;
        }

        if (!$this->confirm('Do you want to continue with the export?', true)) {
            return 0;
        }

        // Run export
        if ($this->option('async')) {
            return $this->runAsync($filters, $limit);
        } else {
            return $this->runSync($exporter, $filters, $limit);
        }
    }

    /**
     * Run export synchronously.
     */
    protected function runSync(CsvExporter $exporter, array $filters, ?int $limit): int
    {
        $this->info('Exporting data...');

        try {
            $filePath = $exporter->exportScrapedData($filters, $limit);
            
            $this->info("Data exported successfully to: {$filePath}");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Export failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Run export asynchronously.
     */
    protected function runAsync(array $filters, ?int $limit): int
    {
        $filename = 'scraped_data_' . now()->format('Y-m-d_His') . '.csv';
        $filePath = storage_path("app/exports/{$filename}");

        \App\Jobs\ExportScrapedDataJob::dispatch($filePath, $limit, $filters);

        $this->info('Export job dispatched to queue');
        $this->info("File will be saved to: {$filePath}");

        return 0;
    }
}
