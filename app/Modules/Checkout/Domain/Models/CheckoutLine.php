<?php

namespace App\Modules\Checkout\Domain\Models;

use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Promotions\Domain\Models\Promotion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'checkout_session_id', 'store_id', 'store_listing_id', 'product_id', 'product_variant_id',
    'price_list_id', 'promotion_id', 'product_name', 'variant_name', 'sku', 'quantity', 'base_unit_price',
    'discount_unit_amount', 'final_unit_price', 'line_base_total', 'line_discount_total', 'line_total',
    'promotion_name', 'currency', 'tax_category_code', 'tax_rate', 'taxable_amount', 'exempt_amount',
    'unaffected_amount', 'tax_amount',
])]
class CheckoutLine extends Model
{
    use HasUuids;

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_session_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(StoreListing::class, 'store_listing_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'reference_id')->where('reference_type', 'CHECKOUT_LINE');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer', 'base_unit_price' => 'decimal:4', 'discount_unit_amount' => 'decimal:4',
            'final_unit_price' => 'decimal:4', 'line_base_total' => 'decimal:4',
            'line_discount_total' => 'decimal:4', 'line_total' => 'decimal:4', 'tax_rate' => 'decimal:6',
            'taxable_amount' => 'decimal:4', 'exempt_amount' => 'decimal:4', 'unaffected_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
        ];
    }
}
