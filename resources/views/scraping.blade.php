<x-layouts.scraping>
    @volt('scraping-dashboard')
    <?php
    use function Livewire\Volt\{state, computed};
    
    state(['url' => '', 'message' => '', 'messageType' => 'info']);
    
    $scrapedData = fn() => \App\Models\ScrapedData::orderBy('scraped_at', 'desc')->limit(50)->get();
    
    $stats = fn() => [
        'total' => \App\Models\ScrapedData::count(),
        'today' => \App\Models\ScrapedData::whereDate('scraped_at', today())->count(),
        'processed' => \App\Models\ScrapedData::where('status', 'processed')->count(),
        'exported' => \App\Models\ScrapedData::where('status', 'exported')->count(),
    ];
    
    $scrape = function() {
        $this->validate(['url' => 'required|string']);
        $this->message = '';
        
        try {
            // Add https:// if no protocol specified
            $url = $this->url;
            if (!preg_match('/^https?:\/\//', $url)) {
                $url = 'https://' . $url;
            }
            
            $proxyManager = app(\App\Services\ProxyManager::class);
            $scraper = new \App\Services\Scrapers\QuotesScraper($proxyManager);
            $data = $scraper->scrape($url);
            
            if ($data) {
                $this->message = '✅ Datos scrapeados exitosamente';
                $this->messageType = 'success';
                $this->url = '';
                $this->js('setTimeout(() => window.location.reload(), 1000)');
            } else {
                // Get last scraping log to show detailed error
                $lastLog = \App\Models\ScrapingLog::where('url', $url)
                    ->latest()
                    ->first();
                
                $errorDetail = 'No se pudo scrapear la URL después de múltiples intentos';
                if ($lastLog && $lastLog->error_message) {
                    $errorDetail .= ': ' . $lastLog->error_message;
                }
                if ($lastLog && $lastLog->response_code) {
                    $errorDetail .= ' (HTTP ' . $lastLog->response_code . ')';
                }
                
                \Log::error("Scraping failed for {$url}", [
                    'url' => $url,
                    'log' => $lastLog?->toArray()
                ]);
                
                $this->message = '❌ ' . $errorDetail;
                $this->messageType = 'error';
            }
        } catch (\Exception $e) {
            \Log::error("Error scrapeando {$url}: {$e->getMessage()}", [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->message = '❌ Error: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    };
    
    $deleteRecord = function(int $id) {
        \App\Models\ScrapedData::find($id)?->delete();
        $this->message = '✅ Registro eliminado';
        $this->messageType = 'success';
        $this->js('setTimeout(() => window.location.reload(), 500)');
    };
    
    $clearAll = function() {
        \App\Models\ScrapedData::truncate();
        $this->message = '✅ Todos los registros eliminados';
        $this->messageType = 'success';
        $this->js('setTimeout(() => window.location.reload(), 500)');
    };
    
    $exportCsv = function() {
        try {
            $exporter = new \App\Services\CsvExporter();
            $filename = $exporter->export();
            $this->message = '✅ Exportado: ' . basename($filename);
            $this->messageType = 'success';
            
            \App\Models\ScrapedData::where('status', 'processed')->update(['status' => 'exported']);
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

            {{-- Scraping Form --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-8">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                    Scrapear Nueva URL
                </h2>

                <form wire:submit="scrape">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <input 
                                type="text" 
                                wire:model="url"
                                placeholder="example.com o https://example.com"
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
                    Datos Scrapeados ({{ count($this->scrapedData()) }} últimos)
                </h2>
                <div class="flex gap-2">
                    <button 
                        wire:click="$refresh"
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Preview Card</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stats</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($this->scrapedData() as $record)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    {{-- Facebook-style Preview Card --}}
                                    <td class="px-6 py-4">
                                        @php
                                            $og = isset($record->data['og']) && is_array($record->data['og']) 
                                                ? $record->data['og'] 
                                                : [];
                                            $ogImage = $og['image'] ?? null;
                                            $ogTitle = $og['title'] ?? $record->data['title'] ?? 'Sin título';
                                            $ogDescription = $og['description'] ?? null;
                                            $ogSiteName = $og['site_name'] ?? parse_url($record->source_url, PHP_URL_HOST);
                                        @endphp
                                        
                                        {{-- Card estilo Facebook --}}
                                        <div class="max-w-md border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden bg-white dark:bg-gray-800 hover:shadow-lg transition">
                                            {{-- Imagen --}}
                                            @if($ogImage)
                                                <div class="w-full h-48 bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                                    <img src="{{ $ogImage }}" 
                                                         alt="Preview" 
                                                         class="w-full h-full object-cover"
                                                         onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center\'><span class=\'text-6xl\'>🌐</span></div>'">
                                                </div>
                                            @else
                                                <div class="w-full h-48 bg-gradient-to-br from-blue-100 to-purple-100 dark:from-blue-900 dark:to-purple-900 flex items-center justify-center">
                                                    <span class="text-6xl">🌐</span>
                                                </div>
                                            @endif
                                            
                                            {{-- Contenido --}}
                                            <div class="p-3 bg-gray-50 dark:bg-gray-750">
                                                {{-- Dominio y Proxy --}}
                                                <div class="flex items-center justify-between mb-1">
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">
                                                        {{ $ogSiteName }}
                                                    </div>
                                                    @if(isset($record->data['metadata']['proxy_used']))
                                                        @php $proxy = $record->data['metadata']['proxy_used']; @endphp
                                                        <div class="flex items-center gap-1 text-xs">
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                                                                </svg>
                                                                Proxy {{ $proxy['type'] ?? 'HTTP' }}
                                                            </span>
                                                        </div>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                            🌐 Directo
                                                        </span>
                                                    @endif
                                                </div>
                                                
                                                {{-- Título --}}
                                                <a href="{{ $record->source_url }}" target="_blank" 
                                                   class="block text-base font-semibold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 mb-1 line-clamp-2">
                                                    {{ $ogTitle }}
                                                </a>
                                                
                                                {{-- Descripción --}}
                                                @if($ogDescription)
                                                    <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                                        {{ $ogDescription }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    
                                    {{-- Stats --}}
                                    <td class="px-6 py-4">
                                        @if(isset($record->data['metadata']['proxy_used']))
                                            @php $proxy = $record->data['metadata']['proxy_used']; @endphp
                                            <div class="space-y-1">
                                                <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">⚡ Proxy Info:</div>
                                                <div class="space-y-1 text-xs text-gray-600 dark:text-gray-400">
                                                    <div>🌐 {{ $proxy['host'] }}:{{ $proxy['port'] }}</div>
                                                    @if(isset($proxy['location']))
                                                        <div>📍 {{ $proxy['location'] }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Sin proxy</span>
                                        @endif
                                    </td>
                                    
                                    {{-- Fecha --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col">
                                            <span class="font-semibold">{{ $record->scraped_at->format('d/m/Y') }}</span>
                                            <span class="text-xs">{{ $record->scraped_at->format('H:i') }}</span>
                                        </div>
                                    </td>
                                    
                                    {{-- Acciones --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <button 
                                            wire:click="deleteRecord({{ $record->id }})"
                                            wire:confirm="¿Eliminar este registro?"
                                            class="px-3 py-2 bg-red-100 hover:bg-red-200 text-red-600 rounded-lg transition"
                                        >
                                            🗑️ Eliminar
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
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
