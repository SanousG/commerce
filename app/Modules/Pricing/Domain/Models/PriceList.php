<?php

namespace App\Modules\Pricing\Domain\Models;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'name', 'code', 'currency', 'status', 'is_default'])]
class PriceList extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(VariantPrice::class);
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'variant_prices', 'price_list_id', 'product_variant_id')
            ->withPivot(['id', 'tenant_id', 'amount'])->withTimestamps();
    }

    public function storeDefaults(): HasMany
    {
        return $this->hasMany(Store::class, 'default_price_list_id');
    }

    public function storeListings(): HasMany
    {
        return $this->hasMany(StoreListing::class);
    }

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'status' => PriceListStatus::class];
    }
}
