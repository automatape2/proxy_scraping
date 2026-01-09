<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Scraping Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the web scraping system.
    |
    */

    /*
    | Use proxy for requests
    */
    'use_proxy' => env('SCRAPING_USE_PROXY', true),

    /*
    | Maximum number of retries for failed requests
    */
    'max_retries' => env('SCRAPING_MAX_RETRIES', 3),

    /*
    | Delay between retries (seconds)
    */
    'retry_delay' => env('SCRAPING_RETRY_DELAY', 2),

    /*
    | Request timeout (seconds)
    */
    'timeout' => env('SCRAPING_TIMEOUT', 30),

    /*
    | Queue name for scraping jobs
    */
    'queue_name' => env('SCRAPING_QUEUE', 'scraping'),

    /*
    | Proxy Configuration
    */
    'proxy' => [
        /*
        | Minimum success rate for active proxies (percentage)
        */
        'min_success_rate' => env('PROXY_MIN_SUCCESS_RATE', 70),

        /*
        | Maximum consecutive failures before banning
        */
        'max_consecutive_failures' => env('PROXY_MAX_CONSECUTIVE_FAILURES', 5),

        /*
        | Proxy rotation strategy: 'random', 'weighted', 'round-robin'
        */
        'rotation_strategy' => env('PROXY_ROTATION_STRATEGY', 'weighted'),
    ],

    /*
    | Rate Limiting
    */
    'rate_limit' => [
        /*
        | Maximum requests per minute
        */
        'max_requests_per_minute' => env('SCRAPING_MAX_RPM', 60),

        /*
        | Delay between requests (milliseconds)
        */
        'delay_between_requests' => env('SCRAPING_REQUEST_DELAY', 1000),
    ],

    /*
    | Export Configuration
    */
    'export' => [
        /*
        | Default export format
        */
        'default_format' => 'csv',

        /*
        | CSV delimiter
        */
        'csv_delimiter' => ',',

        /*
        | Export directory (relative to storage/app)
        */
        'export_directory' => 'exports',

        /*
        | Chunk size for large exports
        */
        'chunk_size' => 1000,
    ],

    /*
    | Logging
    */
    'logging' => [
        /*
        | Enable detailed logging
        */
        'enabled' => env('SCRAPING_LOG_ENABLED', true),

        /*
        | Log channel
        */
        'channel' => env('SCRAPING_LOG_CHANNEL', 'stack'),

        /*
        | Log level
        */
        'level' => env('SCRAPING_LOG_LEVEL', 'info'),
    ],

];
