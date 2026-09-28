<?php

namespace App\Modules\Tax\Application;

use App\Modules\Tax\Domain\Models\TaxCategory;
use App\Modules\Tenancy\Domain\Models\Tenant;

class SaveTaxCategory
{
    public function create(Tenant $tenant, array $data): TaxCategory
    {
        return $tenant->taxCategories()->create($data);
    }

    public function update(TaxCategory $category, array $data): TaxCategory
    {
        $category->update($data);

        return $category->refresh();
    }
}
