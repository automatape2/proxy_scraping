<?php

namespace App\Console\Commands;

use App\Services\ProxyManager;
use Illuminate\Console\Command;

class ProxyManagementCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'proxy:manage
                            {action : The action to perform (add, test, list, stats, cleanup, import)}
                            {--host= : Proxy host}
                            {--port= : Proxy port}
                            {--protocol=http : Proxy protocol (http, https, socks5)}
                            {--username= : Proxy username}
                            {--password= : Proxy password}
                            {--file= : File path for importing proxies}
                            {--days=7 : Days for cleanup}';

    /**
     * The console command description.
     */
    protected $description = 'Manage scraping proxies';

    /**
     * Execute the console command.
     */
    public function handle(ProxyManager $proxyManager): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'add' => $this->addProxy($proxyManager),
            'test' => $this->testProxies($proxyManager),
            'list' => $this->listProxies($proxyManager),
            'stats' => $this->showStats($proxyManager),
            'cleanup' => $this->cleanup($proxyManager),
            'import' => $this->importProxies($proxyManager),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    /**
     * Add a new proxy.
     */
    protected function addProxy(ProxyManager $proxyManager): int
    {
        $host = $this->option('host');
        $port = $this->option('port');

        if (!$host || !$port) {
            $this->error('Host and port are required');
            return 1;
        }

        $this->info('Adding proxy...');

        $proxy = $proxyManager->addProxy(
            $host,
            (int) $port,
            $this->option('protocol'),
            $this->option('username'),
            $this->option('password')
        );

        $this->info("Proxy added successfully: {$proxy->host}:{$proxy->port} (Status: {$proxy->status})");

        return 0;
    }

    /**
     * Test all proxies.
     */
    protected function testProxies(ProxyManager $proxyManager): int
    {
        $this->info('Testing all proxies...');

        $proxies = \App\Models\Proxy::all();
        $bar = $this->output->createProgressBar($proxies->count());

        foreach ($proxies as $proxy) {
            $result = $proxyManager->testProxy($proxy);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Testing completed');

        return 0;
    }

    /**
     * List all proxies.
     */
    protected function listProxies(ProxyManager $proxyManager): int
    {
        $proxies = \App\Models\Proxy::orderByDesc('success_rate')->get();

        if ($proxies->isEmpty()) {
            $this->warn('No proxies found');
            return 0;
        }

        $this->table(
            ['ID', 'Host', 'Port', 'Protocol', 'Status', 'Success Rate', 'Avg Response', 'Last Used'],
            $proxies->map(fn($p) => [
                $p->id,
                $p->host,
                $p->port,
                $p->protocol,
                $p->status,
                number_format($p->success_rate, 2) . '%',
                $p->response_time_avg . 'ms',
                $p->last_used_at?->diffForHumans() ?? 'Never',
            ])
        );

        return 0;
    }

    /**
     * Show proxy statistics.
     */
    protected function showStats(ProxyManager $proxyManager): int
    {
        $stats = $proxyManager->getStatistics();

        $this->info('Proxy Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Proxies', $stats['total']],
                ['Active', $stats['active']],
                ['Inactive', $stats['inactive']],
                ['Banned', $stats['banned']],
                ['Testing', $stats['testing']],
                ['Avg Success Rate', number_format($stats['avg_success_rate'], 2) . '%'],
                ['Avg Response Time', number_format($stats['avg_response_time'], 0) . 'ms'],
            ]
        );

        return 0;
    }

    /**
     * Cleanup old proxies.
     */
    protected function cleanup(ProxyManager $proxyManager): int
    {
        $days = (int) $this->option('days');
        
        $this->info("Cleaning up proxies inactive for {$days} days...");
        
        $count = $proxyManager->cleanup($days);
        
        $this->info("Removed {$count} proxies");

        return 0;
    }

    /**
     * Import proxies from file.
     */
    protected function importProxies(ProxyManager $proxyManager): int
    {
        $file = $this->option('file');

        if (!$file || !file_exists($file)) {
            $this->error('File not found');
            return 1;
        }

        $this->info('Importing proxies...');

        // Read file (format: host:port or host:port:username:password)
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $proxyList = [];

        foreach ($lines as $line) {
            $parts = explode(':', trim($line));
            
            if (count($parts) >= 2) {
                $proxyList[] = [
                    'host' => $parts[0],
                    'port' => (int) $parts[1],
                    'username' => $parts[2] ?? null,
                    'password' => $parts[3] ?? null,
                ];
            }
        }

        $count = $proxyManager->importProxies($proxyList);

        $this->info("Imported {$count} proxies");

        return 0;
    }
}
