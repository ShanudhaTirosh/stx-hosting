<?php

namespace Database\Seeders;

use App\Helpers\SslHelper;
use App\Models\Server;
use Illuminate\Database\Seeder;

class CaSslCertSeeder extends Seeder
{
    public function run()
    {
        Server::chunk(200, function ($servers) {
            foreach ($servers as $server) {
                $existingCaCert = $server->sslCertificates()->where('is_ca_certificate', true)->first();

                if (! $existingCaCert) {
                    $caCert = SslHelper::generateSslCertificate(
                        commonName: 'STX Hosting CA Certificate',
                        serverId: $server->id,
                        isCaCertificate: true,
                        validityDays: 10 * 365
                    );
                } else {
                    $caCert = $existingCaCert;
                }
                $commands = SslHelper::caCertificateFileCommands($caCert->ssl_certificate);

                if ($server->isFunctional()) {
                    try {
                        remote_process($commands, $server);
                    } catch (\Throwable $e) {
                        // Ignore SSH failure during seed
                    }
                }
            }
        });
    }
}
