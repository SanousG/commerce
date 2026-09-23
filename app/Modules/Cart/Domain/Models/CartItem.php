<?php

namespace App\Modules\Cart\Domain\Models;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'store_id', 'cart_id', 'store_listing_id', 'product_id', 'product_variant_id', 'quantity'])]
class CartItem extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(StoreListing::class, 'store_listing_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }
}
