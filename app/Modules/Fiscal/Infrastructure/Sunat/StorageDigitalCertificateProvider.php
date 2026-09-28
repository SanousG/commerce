<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use App\Modules\Fiscal\Application\Contracts\DigitalCertificateProvider;
use App\Modules\Fiscal\Application\Data\DigitalCertificate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\FilesystemManager;
use RuntimeException;

final class StorageDigitalCertificateProvider implements DigitalCertificateProvider
{
    public function __construct(private Repository $config, private FilesystemManager $filesystems) {}

    /**
     * The Beta configuration still reads a single PKCS#12 file; the tenant id
     * is part of the contract so tenant-scoped certificates can be introduced
     * without touching callers. No request payload can influence it.
     */
    public function certificateFor(string $tenantId): DigitalCertificate
    {
        $path = trim((string) $this->config->get('sunat.certificate.path'));
        $password = (string) $this->config->get('sunat.certificate.password');

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            throw new RuntimeException('SUNAT_CERTIFICATE_PATH_INVALID');
        }

        $contents = $this->filesystems->disk((string) $this->config->get('sunat.storage_disk'))->get($path);
        $certificates = [];
        if (! openssl_pkcs12_read($contents, $certificates, $password)) {
            throw new RuntimeException('SUNAT_CERTIFICATE_COULD_NOT_BE_READ');
        }

        $parsed = openssl_x509_parse($certificates['cert']);
        $privateKey = openssl_pkey_get_private($certificates['pkey']);
        if (! is_array($parsed) || $privateKey === false || ! isset($parsed['validFrom_time_t'], $parsed['validTo_time_t'])) {
            throw new RuntimeException('SUNAT_CERTIFICATE_INVALID');
        }
        $privateKeyPem = '';
        if (! openssl_pkey_export($privateKey, $privateKeyPem)) {
            throw new RuntimeException('SUNAT_CERTIFICATE_PRIVATE_KEY_INVALID');
        }

        $certificate = new DigitalCertificate(
            $certificates['cert'],
            $privateKeyPem,
            CarbonImmutable::createFromTimestampUTC($parsed['validFrom_time_t']),
            CarbonImmutable::createFromTimestampUTC($parsed['validTo_time_t']),
            strtolower((string) openssl_x509_fingerprint($certificates['cert'], 'sha256')),
            $this->subjectRuc($parsed),
        );
        $certificate->assertCurrentlyValid();

        return $certificate;
    }

    /** @param array<string, mixed> $parsed */
    private function subjectRuc(array $parsed): ?string
    {
        $subject = '';
        foreach ((array) ($parsed['subject'] ?? []) as $value) {
            $subject .= ' '.$value;
        }

        return preg_match('/(?<!\d)(\d{11})(?!\d)/', $subject, $matches) === 1 ? $matches[1] : null;
    }
}
