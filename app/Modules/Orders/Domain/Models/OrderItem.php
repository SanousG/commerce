<?php

namespace App\Modules\Orders\Domain\Models;

use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Checkout\Domain\Models\CheckoutLine;
use App\Modules\Fiscal\Domain\Models\FiscalDocumentItem;
use App\Modules\Fulfillment\Domain\Models\FulfillmentItem;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Returns\Domain\Models\ReturnItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'store_id', 'order_id', 'checkout_line_id', 'store_listing_id', 'product_id',
    'product_variant_id', 'product_name', 'variant_name', 'sku', 'quantity', 'price_list_id',
    'base_unit_price', 'discount_unit_amount', 'final_unit_price', 'line_base_total',
    'line_discount_total', 'line_total', 'promotion_id', 'promotion_name', 'tax_category_code', 'tax_rate',
    'taxable_amount', 'exempt_amount', 'unaffected_amount', 'tax_amount',
])]
class OrderItem extends Model
{
    use HasUuids;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function checkoutLine(): BelongsTo
    {
        return $this->belongsTo(CheckoutLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'reference_id', 'checkout_line_id')
            ->where('reference_type', 'CHECKOUT_LINE');
    }

    public function fulfillmentItems(): HasMany
    {
        return $this->hasMany(FulfillmentItem::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function fiscalDocumentItems(): HasMany
    {
        return $this->hasMany(FiscalDocumentItem::class);
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
