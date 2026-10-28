<?php

namespace App\Modules\Storefront\Application;

use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Stores\Domain\Models\StorefrontPublication;
use App\Modules\Tenancy\Domain\Models\Tenant;

final class PublicStoreContext
{
    private ?StorefrontPublication $publication = null;

    public function set(StorefrontPublication $publication): void
    {
        $this->publication = $publication;
    }

    public function publication(): ?StorefrontPublication
    {
        return $this->publication;
    }

    public function store(): ?Store
    {
        return $this->publication?->store;
    }

    public function tenant(): ?Tenant
    {
        return $this->publication?->tenant;
    }

    public function hasStore(): bool
    {
        return $this->publication !== null;
    }

    public function clear(): void
    {
        $this->publication = null;
    }
}
