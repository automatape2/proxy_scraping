# Sistema de Web Scraping Robusto para Laravel

Sistema completo de web scraping con gestión avanzada de proxies, manejo de errores y procesamiento de alto volumen.

## Características

- ✅ **Gestión avanzada de proxies** con rotación automática y monitoreo de rendimiento
- ✅ **Sistema de colas** para procesamiento de alto volumen (millones de registros)
- ✅ **Manejo robusto de errores** con reintentos automáticos
- ✅ **Exportación a CSV** con formato personalizable
- ✅ **Logging completo** de todas las operaciones
- ✅ **Arquitectura extensible** con scrapers personalizables
- ✅ **Rate limiting** para evitar bloqueos
- ✅ **Comandos Artisan** para gestión completa

## Instalación

### 1. Instalar Dependencias

```bash
composer require symfony/dom-crawler symfony/css-selector
```

### 2. Ejecutar Migraciones

```bash
php artisan migrate
```

### 3. Configurar Variables de Entorno

Agregar al archivo `.env`:

```env
# Scraping Configuration
SCRAPING_USE_PROXY=true
SCRAPING_MAX_RETRIES=3
SCRAPING_RETRY_DELAY=2
SCRAPING_TIMEOUT=30
SCRAPING_QUEUE=scraping

# Proxy Configuration
PROXY_MIN_SUCCESS_RATE=70
PROXY_MAX_CONSECUTIVE_FAILURES=5

# Rate Limiting
SCRAPING_MAX_RPM=60
SCRAPING_REQUEST_DELAY=1000

# Logging
SCRAPING_LOG_ENABLED=true
SCRAPING_LOG_LEVEL=info
```

### 4. Configurar Colas

Actualizar `config/queue.php` o usar Redis/Database:

```bash
php artisan queue:table
php artisan migrate
```

## Uso

### Gestión de Proxies

#### Agregar un proxy manualmente:

```bash
php artisan proxy:manage add --host=192.168.1.1 --port=8080 --protocol=http
```

#### Agregar proxy con autenticación:

```bash
php artisan proxy:manage add --host=192.168.1.1 --port=8080 --username=user --password=pass
```

#### Importar proxies desde archivo:

Crear un archivo `proxies.txt` con el formato:
```
host:port
host:port:username:password
```

```bash
php artisan proxy:manage import --file=proxies.txt
```

#### Listar todos los proxies:

```bash
php artisan proxy:manage list
```

#### Ver estadísticas de proxies:

```bash
php artisan proxy:manage stats
```

#### Probar todos los proxies:

```bash
php artisan proxy:manage test
```

#### Limpiar proxies inactivos:

```bash
php artisan proxy:manage cleanup --days=7
```

### Web Scraping

#### Scraping de una URL individual (sincrónico):

```bash
php artisan scrape:run --url=https://example.com/page
```

#### Scraping de múltiples URLs desde archivo (sincrónico):

```bash
php artisan scrape:run --file=urls.txt
```

#### Scraping asincrónico (usando colas):

```bash
php artisan scrape:run --file=urls.txt --async
```

#### Especificar un scraper personalizado:

```bash
php artisan scrape:run --file=urls.txt --scraper=App\\Services\\Scrapers\\CustomScraper
```

### Exportación de Datos

#### Exportar todos los datos procesados:

```bash
php artisan scrape:export
```

#### Exportar con filtros de fecha:

```bash
php artisan scrape:export --from=2026-01-01 --to=2026-01-31
```

#### Exportar con límite:

```bash
php artisan scrape:export --limit=10000
```

#### Exportar de forma asíncrona:

```bash
php artisan scrape:export --async
```

### Trabajar con Colas

#### Iniciar worker de colas:

```bash
php artisan queue:work --queue=scraping
```

#### Iniciar múltiples workers para alto volumen:

```bash
php artisan queue:work --queue=scraping --tries=3 &
php artisan queue:work --queue=scraping --tries=3 &
php artisan queue:work --queue=scraping --tries=3 &
```

#### Monitorear colas:

```bash
php artisan queue:monitor scraping
```

## Crear un Scraper Personalizado

### 1. Crear clase de scraper

Crear un archivo en `app/Services/Scrapers/MyCustomScraper.php`:

```php
<?php

namespace App\Services\Scrapers;

use App\Models\ScrapedData;

class MyCustomScraper extends BaseScraper
{
    /**
     * Scrape data from URL.
     */
    public function scrape(string $url): ?array
    {
        // Fetch HTML
        $html = $this->fetch($url);
        
        if (!$html) {
            return null;
        }

        // Parse HTML
        $crawler = $this->parse($html);
        
        // Extract data
        $rawData = [
            'title' => $this->extractText($crawler, 'h1.title'),
            'description' => $this->extractText($crawler, '.description'),
            'price' => $this->extractText($crawler, '.price'),
            // ... más campos
        ];

        // Validate
        if (!$this->validateData($rawData)) {
            return null;
        }

        // Process
        $processedData = $this->processData($rawData);

        // Save
        $this->saveData($url, $processedData);

        return $processedData;
    }

    /**
     * Process extracted data.
     */
    protected function processData(array $rawData): array
    {
        return [
            'title' => trim($rawData['title'] ?? ''),
            'description' => trim($rawData['description'] ?? ''),
            'price' => $this->extractPrice($rawData['price'] ?? ''),
            // ... procesar más campos
        ];
    }

    /**
     * Validate data.
     */
    protected function validateData(array $data): bool
    {
        return !empty($data['title']);
    }

    /**
     * Save to database.
     */
    protected function saveData(string $url, array $data): void
    {
        $identifier = md5($url . json_encode($data));

        ScrapedData::updateOrCreate(
            ['unique_identifier' => $identifier],
            [
                'source_url' => $url,
                'data' => $data,
                'status' => 'processed',
                'scraped_at' => now(),
            ]
        );
    }
}
```

### 2. Usar el scraper personalizado

```bash
php artisan scrape:run --url=https://example.com --scraper=App\\Services\\Scrapers\\MyCustomScraper
```

## Manejo de Autenticación

Para sitios que requieren autenticación, puedes extender el `BaseScraper`:

```php
class AuthenticatedScraper extends BaseScraper
{
    protected function setupHeaders(): void
    {
        parent::setupHeaders();
        
        // Agregar headers de autenticación
        $this->headers['Authorization'] = 'Bearer ' . $this->getAuthToken();
    }

    protected function getAuthToken(): string
    {
        // Implementar lógica de autenticación
        // Puede usar cookies, tokens JWT, etc.
        return cache()->remember('auth_token', 3600, function() {
            return $this->performLogin();
        });
    }

    protected function performLogin(): string
    {
        // Realizar login y obtener token
        $response = Http::post('https://example.com/api/login', [
            'username' => config('scraping.auth.username'),
            'password' => config('scraping.auth.password'),
        ]);

        return $response->json()['token'];
    }
}
```

## Estructura de la Base de Datos

### Tabla `proxies`
Almacena información de proxies con métricas de rendimiento.

### Tabla `scraping_logs`
Registra cada intento de scraping (éxito/fallo).

### Tabla `scraped_data`
Almacena los datos extraídos en formato JSON.

## Personalizar Exportación CSV

Editar `app/Services/CsvExporter.php` para cambiar el formato:

```php
protected function getHeaders(): array
{
    return [
        'ID',
        'URL',
        'Tu Campo 1',
        'Tu Campo 2',
        // ... más headers
    ];
}

protected function formatScrapedDataRow(ScrapedData $record): array
{
    $data = $record->data;

    return [
        $record->id,
        $record->source_url,
        $data['campo1'] ?? '',
        $data['campo2'] ?? '',
        // ... más campos
    ];
}
```

## Optimización para Alto Volumen

### 1. Usar Redis para colas:

```env
QUEUE_CONNECTION=redis
```

### 2. Aumentar workers:

```bash
# Usar Supervisor para mantener workers activos
sudo apt-get install supervisor

# Configurar en /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --queue=scraping --tries=3
autostart=true
autorestart=true
numprocs=8
```

### 3. Optimizar base de datos:

```sql
-- Crear índices adicionales si es necesario
CREATE INDEX idx_scraped_data_status_date ON scraped_data(status, scraped_at);
CREATE INDEX idx_proxies_performance ON proxies(status, success_rate, response_time_avg);
```

### 4. Usar chunking para exportaciones grandes:

El sistema ya usa chunking de 1000 registros por defecto.

## Monitoreo y Debugging

### Ver logs en tiempo real:

```bash
tail -f storage/logs/laravel.log
```

### Ver trabajos fallidos:

```bash
php artisan queue:failed
```

### Reintentar trabajos fallidos:

```bash
php artisan queue:retry all
```

### Limpiar trabajos fallidos:

```bash
php artisan queue:flush
```

## Mejores Prácticas

1. **Rotar proxies regularmente** - Ejecutar `php artisan proxy:manage test` periódicamente
2. **Monitorear tasa de éxito** - Revisar estadísticas con `php artisan proxy:manage stats`
3. **Usar rate limiting** - Configurar delays apropiados para evitar bloqueos
4. **Implementar validación robusta** - Verificar datos antes de guardar
5. **Limpiar datos antiguos** - Programar limpieza periódica de logs
6. **Usar colas para alto volumen** - Siempre usar `--async` para grandes cantidades
7. **Supervisar workers** - Usar Supervisor en producción

## Troubleshooting

### Problema: No hay proxies disponibles
```bash
php artisan proxy:manage import --file=proxies.txt
php artisan proxy:manage test
```

### Problema: Trabajos atascados en cola
```bash
php artisan queue:restart
php artisan queue:work --queue=scraping
```

### Problema: Memoria insuficiente
```bash
# Aumentar memoria en php artisan
php -d memory_limit=512M artisan scrape:run --file=urls.txt
```

### Problema: Proxies bloqueados
```bash
# Limpiar proxies bloqueados
php artisan proxy:manage cleanup --days=0
# Importar nuevos proxies
php artisan proxy:manage import --file=new_proxies.txt
```

## Licencia

Este sistema es parte de tu aplicación Laravel.
