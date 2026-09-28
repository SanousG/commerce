<?php

namespace App\Modules\Fiscal\Application\Contracts;

use App\Modules\Fiscal\Application\Data\DigitalCertificate;

interface DigitalCertificateProvider
{
    /**
     * Resolve the signing certificate authorized for the given tenant.
     *
     * The Beta infrastructure still reads a single configured PKCS#12 file;
     * tenant-scoped certificate storage is a pending multi-tenancy step and must
     * never fall back to a value supplied by the request payload.
     */
    public function certificateFor(string $tenantId): DigitalCertificate;
}
