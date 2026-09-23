<?php

namespace App\Modules\Stores\Application;

use App\Modules\Stores\Domain\Models\Store;

readonly class StoreCreationResult
{
    public function __construct(
        public Store $store,
        public bool $isFirstStore,
    ) {}
}
