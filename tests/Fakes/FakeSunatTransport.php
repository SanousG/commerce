<?php

namespace Tests\Fakes;

use App\Modules\Fiscal\Application\Contracts\SunatTransport;
use App\Modules\Fiscal\Application\Data\SunatTransportResult;

final class FakeSunatTransport implements SunatTransport
{
    /** @var list<array{operation: string, tenant_id: string, name: string, contents: string}> */
    public array $requests = [];

    public int $sendBillCalls = 0;

    /** @var list<SunatTransportResult|\Throwable> consumed one per sendBill call, in order */
    private array $script = [];

    public function __construct(private ?SunatTransportResult $result = null, private ?\Throwable $failure = null) {}

    /** @param list<SunatTransportResult|\Throwable> $responses */
    public function script(array $responses): void
    {
        $this->script = $responses;
    }

    public function sendBill(string $tenantId, string $zipFileName, string $zipContents): SunatTransportResult
    {
        $this->requests[] = ['operation' => 'sendBill', 'tenant_id' => $tenantId, 'name' => $zipFileName, 'contents' => $zipContents];
        $this->sendBillCalls++;

        if ($this->script !== []) {
            $next = array_shift($this->script);
            if ($next instanceof \Throwable) {
                throw $next;
            }

            return $next;
        }

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->result ?? new SunatTransportResult;
    }

    public function sendSummary(string $tenantId, string $zipFileName, string $zipContents): SunatTransportResult
    {
        $this->requests[] = ['operation' => 'sendSummary', 'tenant_id' => $tenantId, 'name' => $zipFileName, 'contents' => $zipContents];

        return $this->respond();
    }

    public function getStatus(string $tenantId, string $ticket): SunatTransportResult
    {
        $this->requests[] = ['operation' => 'getStatus', 'tenant_id' => $tenantId, 'name' => $ticket, 'contents' => ''];

        return $this->respond();
    }

    private function respond(): SunatTransportResult
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->result ?? new SunatTransportResult;
    }
}
