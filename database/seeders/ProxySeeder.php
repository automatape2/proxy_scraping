<?php

namespace Database\Seeders;

use App\Models\Proxy;
use Illuminate\Database\Seeder;

class ProxySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Proxies públicos gratuitos para pruebas
        // NOTA: Estos proxies pueden no funcionar siempre. Para producción, usa proxies comerciales.
        $proxies = [
            ['host' => '8.213.128.6', 'port' => 80, 'protocol' => 'http'],
            ['host' => '47.91.45.235', 'port' => 3128, 'protocol' => 'http'],
            ['host' => '47.251.70.179', 'port' => 80, 'protocol' => 'http'],
            ['host' => '103.152.112.162', 'port' => 80, 'protocol' => 'http'],
            ['host' => '158.69.72.138', 'port' => 9300, 'protocol' => 'http'],
        ];

        foreach ($proxies as $proxy) {
            Proxy::create([
                'host' => $proxy['host'],
                'port' => $proxy['port'],
                'protocol' => $proxy['protocol'],
                'status' => 'testing',
            ]);
        }

        $this->command->info('✓ ' . count($proxies) . ' proxies de prueba agregados');
    }
}
