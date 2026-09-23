<?php

namespace App\Modules\Listings\Domain\Models;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'store_listing_id', 'product_id', 'product_variant_id', 'is_enabled'])]
class StoreListingVariant extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(StoreListing::class, 'store_listing_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }
}
