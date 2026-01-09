<?php

namespace App\Livewire;

use App\Models\ScrapedData;
use App\Services\CsvExporter;
use App\Services\Scrapers\QuotesScraper;
use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {
    public string $url = '';
    public bool $scraping = false;
    public string $message = '';
    public string $messageType = 'info'; // success, error, info
    public array $scrapedData = [];
    public array $stats = [
        'total' => 0,
        'today' => 0,
        'processed' => 0,
        'exported' => 0,
    ];

    public function mount(): void
    {
        $this->loadData();
        $this->loadStats();
    }

    public function loadData(): void
    {
        $this->scrapedData = ScrapedData::orderBy('scraped_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn($record) => [
                'id' => $record->id,
                'source_url' => $record->source_url,
                'data' => $record->data,
                'scraped_at' => $record->scraped_at,
            ])
            ->toArray();
    }

    public function loadStats(): void
    {
        $this->stats = [
            'total' => ScrapedData::count(),
            'today' => ScrapedData::whereDate('scraped_at', Carbon::today())->count(),
            'processed' => ScrapedData::where('status', 'processed')->count(),
            'exported' => ScrapedData::where('status', 'exported')->count(),
        ];
    }

    public function scrape(): void
    {
        $this->validate([
            'url' => 'required|url',
        ]);

        $this->scraping = true;
        $this->message = '';

        try {
            $scraper = new QuotesScraper();
            $data = $scraper->scrape($this->url);

            if ($data) {
                $this->message = '✅ Datos scrapeados exitosamente';
                $this->messageType = 'success';
                $this->url = '';
                $this->loadData();
                $this->loadStats();
            } else {
                $this->message = '❌ No se pudo scrapear la URL';
                $this->messageType = 'error';
            }
        } catch (\Exception $e) {
            $this->message = '❌ Error: ' . $e->getMessage();
            $this->messageType = 'error';
        } finally {
            $this->scraping = false;
        }
    }

    public function deleteRecord(int $id): void
    {
        ScrapedData::find($id)?->delete();
        $this->loadData();
        $this->loadStats();
        $this->message = '✅ Registro eliminado';
        $this->messageType = 'success';
    }

    public function clearAll(): void
    {
        ScrapedData::truncate();
        $this->loadData();
        $this->loadStats();
        $this->message = '✅ Todos los registros eliminados';
        $this->messageType = 'success';
    }

    public function exportCsv(): void
    {
        try {
            $exporter = new CsvExporter();
            $filename = $exporter->export();
            $this->message = '✅ Exportado: ' . basename($filename);
            $this->messageType = 'success';
            
            // Update status of exported records
            ScrapedData::where('status', 'processed')
                ->update(['status' => 'exported']);
            
            $this->loadStats();
        } catch (\Exception $e) {
            $this->message = '❌ Error al exportar: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }
}; 
