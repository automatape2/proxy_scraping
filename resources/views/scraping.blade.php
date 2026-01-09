<x-layouts.scraping>
    @volt('scraping-dashboard')
    <?php
    use function Livewire\Volt\{state};
    
    state(['url' => '', 'message' => '', 'messageType' => 'info']);
    
    $scrapedData = \App\Models\ScrapedData::orderBy('scraped_at', 'desc')->limit(50)->get();
    $stats = [
        'total' => \App\Models\ScrapedData::count(),
        'today' => \App\Models\ScrapedData::whereDate('scraped_at', today())->count(),
        'processed' => \App\Models\ScrapedData::where('status', 'processed')->count(),
        'exported' => \App\Models\ScrapedData::where('status', 'exported')->count(),
    ];
    
    $loadData = function() {
        $this->scrapedData = \App\Models\ScrapedData::orderBy('scraped_at', 'desc')->limit(50)->get();
    };
    
    $loadStats = function() {
        $this->stats = [
            'total' => \App\Models\ScrapedData::count(),
            'today' => \App\Models\ScrapedData::whereDate('scraped_at', today())->count(),
            'processed' => \App\Models\ScrapedData::where('status', 'processed')->count(),
            'exported' => \App\Models\ScrapedData::where('status', 'exported')->count(),
        ];
    };
    
    $scrape = function() {
        $this->validate(['url' => 'required|url']);
        $this->message = '';
        
        try {
            $proxyManager = app(\App\Services\ProxyManager::class);
            $scraper = new \App\Services\Scrapers\QuotesScraper($proxyManager);
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
        }
    };
    
    $deleteRecord = function(int $id) {
        \App\Models\ScrapedData::find($id)?->delete();
        $this->loadData();
        $this->loadStats();
        $this->message = '✅ Registro eliminado';
        $this->messageType = 'success';
    };
    
    $clearAll = function() {
        \App\Models\ScrapedData::truncate();
        $this->loadData();
        $this->loadStats();
        $this->message = '✅ Todos los registros eliminados';
        $this->messageType = 'success';
    };
    
    $exportCsv = function() {
        try {
            $exporter = new \App\Services\CsvExporter();
            $filename = $exporter->export();
            $this->message = '✅ Exportado: ' . basename($filename);
            $this->messageType = 'success';
            
            \App\Models\ScrapedData::where('status', 'processed')->update(['status' => 'exported']);
            $this->loadStats();
        } catch (\Exception $e) {
            $this->message = '❌ Error al exportar: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    };
    ?>
    
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Header --}}
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                    🕷️ Web Scraping Dashboard
                </h1>
                <p class="text-gray-600 dark:text-gray-400">
                    Extrae y visualiza datos de cualquier sitio web
                </p>
            </div>

            {{-- Stats Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total</div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['total'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Hoy</div>
                    <div class="text-3xl font-bold text-blue-600">{{ $stats['today'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Procesados</div>
                    <div class="text-3xl font-bold text-green-600">{{ $stats['processed'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Exportados</div>
                    <div class="text-3xl font-bold text-purple-600">{{ $stats['exported'] }}</div>
                </div>
            </div>

            {{-- Scraping Form --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-8">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                    Scrapear Nueva URL
                </h2>

                <form wire:submit="scrape">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <input 
                                type="url" 
                                wire:model="url"
                                placeholder="https://example.com"
                                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                            >
                            @error('url') 
                                <span class="text-red-500 text-sm mt-1">{{ $message }}</span> 
                            @enderror
                        </div>
                        <button 
                            type="submit"
                            class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg disabled:bg-gray-400 disabled:cursor-not-allowed transition"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="scrape">🚀 Scrapear</span>
                            <span wire:loading wire:target="scrape">⏳ Scrapeando...</span>
                        </button>
                    </div>
                </form>

                @if($message)
                    <div class="mt-4 p-4 rounded-lg {{ $messageType === 'success' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : ($messageType === 'error' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200') }}">
                        {{ $message }}
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                    Datos Scrapeados ({{ count($scrapedData) }} últimos)
                </h2>
                <div class="flex gap-2">
                    <button 
                        wire:click="loadData"
                        class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition"
                    >
                        🔄 Actualizar
                    </button>
                    <button 
                        wire:click="exportCsv"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition"
                    >
                        📥 Exportar CSV
                    </button>
                    <button 
                        wire:click="clearAll"
                        wire:confirm="¿Estás seguro de eliminar todos los registros?"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition"
                    >
                        🗑️ Limpiar Todo
                    </button>
                </div>
            </div>

            {{-- Data Table --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">URL</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Título</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Headings</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Links</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Imágenes</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Palabras</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($scrapedData as $record)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ $record->id }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                        <a href="{{ $record->source_url }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 truncate block max-w-xs" title="{{ $record->source_url }}">
                                            {{ Str::limit($record->source_url, 40) }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                        {{ Str::limit($record->data['title'] ?? 'N/A', 30) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded">
                                            {{ $record->data['headings_count'] ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <span class="px-2 py-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded">
                                            {{ $record->data['links_count'] ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <span class="px-2 py-1 bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 rounded">
                                            {{ $record->data['images_count'] ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ $record->data['metadata']['word_count'] ?? 0 }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $record->scraped_at->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <button 
                                            wire:click="deleteRecord({{ $record->id }})"
                                            wire:confirm="¿Eliminar este registro?"
                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                        >
                                            🗑️
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        <div class="text-6xl mb-4">📭</div>
                                        <p class="text-lg">No hay datos scrapeados todavía</p>
                                        <p class="text-sm mt-2">Ingresa una URL arriba para comenzar</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    @endvolt
</x-layouts.scraping>
