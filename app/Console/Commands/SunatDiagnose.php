<?php

namespace App\Console\Commands;

use App\Modules\Fiscal\Application\Contracts\DigitalCertificateProvider;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemManager;

final class SunatDiagnose extends Command
{
    protected $signature = 'sunat:diagnose {--tenant= : Tenant id used to resolve tenant-aware fiscal credentials}';

    protected $description = 'Check local SUNAT integration prerequisites without exposing secrets';

    public function handle(FilesystemManager $filesystems, DigitalCertificateProvider $certificates): int
    {
        $disk = $filesystems->disk((string) config('sunat.storage_disk'));
        $checks = [
            'Environment' => in_array(config('sunat.environment'), ['BETA', 'PRODUCTION'], true),
            'Production guard' => config('sunat.environment') !== 'PRODUCTION' || config('sunat.production_enabled') === true,
            'Credentials configured' => filled(config('sunat.credentials.username')) && filled(config('sunat.credentials.password')),
            'SOAP extension' => extension_loaded('soap'),
            'DOM/XML extension' => extension_loaded('dom') && extension_loaded('libxml'),
            'OpenSSL extension' => extension_loaded('openssl'),
            'ZIP extension' => extension_loaded('zip'),
            'Invoice XSD' => $disk->exists((string) config('sunat.xsd_invoice')),
        ];

        try {
            $probe = '.diagnose-'.bin2hex(random_bytes(8));
            $disk->put($probe, 'ok');
            $checks['Private storage writable'] = $disk->delete($probe);
        } catch (\Throwable) {
            $checks['Private storage writable'] = false;
        }

        try {
            $certificate = $certificates->certificateFor((string) $this->option('tenant'));
            $checks['Certificate readable/current'] = true;
            $this->line('Certificate fingerprint: '.$certificate->fingerprint);
            $this->line('Certificate not before: '.$certificate->notBefore->toIso8601String());
            $this->line('Certificate expires at: '.$certificate->expiresAt->toIso8601String());
        } catch (\Throwable) {
            $checks['Certificate readable/current'] = false;
        }

        $this->table(['Check', 'Result'], collect($checks)->map(fn (bool $ok, string $name): array => [$name, $ok ? 'OK' : 'FAIL'])->values()->all());

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
