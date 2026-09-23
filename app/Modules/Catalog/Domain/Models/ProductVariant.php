<?php

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Listings\Domain\Models\StoreListingVariant;
use App\Modules\Pricing\Domain\Models\VariantPrice;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'product_id', 'sku', 'barcode', 'name', 'is_default', 'status'])]
class ProductVariant extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'product_variant_option_values',
            'product_variant_id',
            'product_option_value_id',
        )->withPivot(['product_id', 'product_option_id']);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(VariantPrice::class);
    }

    public function inventoryLevels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class);
    }

    public function storeListingVariants(): HasMany
    {
        return $this->hasMany(StoreListingVariant::class);
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'status' => ProductVariantStatus::class,
        ];
    }
}
