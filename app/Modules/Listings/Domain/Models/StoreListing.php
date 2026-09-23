<?php

namespace App\Modules\Listings\Domain\Models;

use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'store_id', 'product_id', 'price_list_id', 'status', 'storefront_slug', 'published_at'])]
class StoreListing extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(StoreListingVariant::class);
    }

    protected function casts(): array
    {
        return ['status' => StoreListingStatus::class, 'published_at' => 'immutable_datetime'];
    }
}
