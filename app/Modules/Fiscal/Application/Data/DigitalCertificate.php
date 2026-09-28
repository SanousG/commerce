<?php

namespace App\Modules\Fiscal\Application\Data;

use Carbon\CarbonImmutable;

final readonly class DigitalCertificate
{
    public function __construct(
        public string $x509,
        public string $privateKey,
        public CarbonImmutable $notBefore,
        public CarbonImmutable $expiresAt,
        public string $fingerprint,
        /** 11-digit RUC found in the certificate subject, or null when absent. */
        public ?string $subjectRuc = null,
    ) {}

    public function assertCurrentlyValid(): void
    {
        $now = CarbonImmutable::now();

        if ($now->isBefore($this->notBefore) || ! $now->isBefore($this->expiresAt)) {
            throw new \RuntimeException('SUNAT_SIGNING_CERTIFICATE_NOT_CURRENTLY_VALID');
        }
    }
}
