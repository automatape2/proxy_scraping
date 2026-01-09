## Instrucciones de Instalación Adicionales

Después de configurar el sistema de scraping, ejecuta los siguientes comandos:

### 1. Instalar las dependencias necesarias:

```bash
composer require symfony/dom-crawler symfony/css-selector guzzlehttp/guzzle
```

### 2. Ejecutar las migraciones:

```bash
php artisan migrate
```

### 3. Configurar las variables de entorno:

Agrega estas líneas a tu archivo `.env`:

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

### 4. Crear la tabla de jobs (si usas database para colas):

```bash
php artisan queue:table
php artisan queue:batches-table
php artisan migrate
```

### 5. Crear directorio de exportaciones:

```bash
mkdir -p storage/app/exports
chmod -R 775 storage/app/exports
```

### 6. Probar el sistema:

```bash
# Ver comandos disponibles
php artisan list | grep -E "scrape|proxy"

# Ver estadísticas de proxies (estará vacío inicialmente)
php artisan proxy:manage stats
```

### 7. Iniciar el worker de colas (en producción):

```bash
php artisan queue:work --queue=scraping --tries=3
```

## Siguiente Paso

Lee el archivo [README_SCRAPING.md](README_SCRAPING.md) para instrucciones detalladas de uso.
