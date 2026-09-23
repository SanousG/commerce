<?php

namespace App\Modules\Orders\Domain\Models;

use App\Modules\Checkout\Domain\Models\CheckoutLine;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'store_id', 'order_id', 'checkout_line_id', 'store_listing_id', 'product_id',
    'product_variant_id', 'product_name', 'variant_name', 'sku', 'quantity', 'price_list_id',
    'base_unit_price', 'discount_unit_amount', 'final_unit_price', 'line_base_total',
    'line_discount_total', 'line_total', 'promotion_id', 'promotion_name',
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

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'reference_id', 'checkout_line_id')
            ->where('reference_type', 'CHECKOUT_LINE');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer', 'base_unit_price' => 'decimal:4', 'discount_unit_amount' => 'decimal:4',
            'final_unit_price' => 'decimal:4', 'line_base_total' => 'decimal:4',
            'line_discount_total' => 'decimal:4', 'line_total' => 'decimal:4',
        ];
    }
}
