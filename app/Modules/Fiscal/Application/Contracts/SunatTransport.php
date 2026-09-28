<?php

namespace App\Modules\Fiscal\Application\Contracts;

use App\Modules\Fiscal\Application\Data\SunatTransportResult;

interface SunatTransport
{
    public function sendBill(string $tenantId, string $zipFileName, string $zipContents): SunatTransportResult;

    public function sendSummary(string $tenantId, string $zipFileName, string $zipContents): SunatTransportResult;

    public function getStatus(string $tenantId, string $ticket): SunatTransportResult;
}
