<?php

namespace App\Modules\Fiscal\Application\Contracts;

use App\Modules\Fiscal\Application\Data\SunatCredentials;

interface SunatCredentialsProvider
{
    /**
     * Resolve the SUNAT SOL credentials authorized for the given tenant.
     *
     * The Beta infrastructure still reads a single configured credential pair;
     * tenant-scoped credential storage is a pending multi-tenancy step and must
     * never fall back to a value supplied by the request payload.
     */
    public function credentialsFor(string $tenantId): SunatCredentials;
}
