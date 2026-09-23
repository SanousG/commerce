<?php

namespace App\Modules\Stores\Application;

use App\Modules\Stores\Domain\Models\Store;

class StoreContext
{
    public const SESSION_KEY = 'current_store_id';

    private ?Store $store = null;

    public function set(Store $store): void
    {
        $this->store = $store;
    }

    public function get(): ?Store
    {
        return $this->store;
    }

    public function id(): ?string
    {
        return $this->store?->getKey();
    }

    public function hasStore(): bool
    {
        return $this->store !== null;
    }

    public function clear(): void
    {
        $this->store = null;
    }
}
