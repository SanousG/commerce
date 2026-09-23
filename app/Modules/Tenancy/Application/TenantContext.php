<?php

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Domain\Models\Tenant;

class TenantContext
{
    public const SESSION_KEY = 'current_tenant_id';

    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->getKey();
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
