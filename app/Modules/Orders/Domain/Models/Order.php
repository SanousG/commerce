<?php

namespace App\Modules\Orders\Domain\Models;

use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Orders\Domain\Enums\OrderFulfillmentStatus;
use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'store_id', 'checkout_session_id', 'cart_id', 'customer_id', 'order_number', 'status',
    'payment_status', 'fulfillment_status', 'currency', 'customer_name', 'email', 'phone',
    'shipping_recipient_name', 'shipping_phone', 'shipping_country_code', 'shipping_region', 'shipping_city',
    'shipping_district', 'shipping_address_line_1', 'shipping_address_line_2', 'shipping_postal_code',
    'shipping_reference', 'billing_recipient_name', 'billing_phone', 'billing_country_code', 'billing_region',
    'billing_city', 'billing_district', 'billing_address_line_1', 'billing_address_line_2',
    'billing_postal_code', 'billing_reference', 'delivery_type', 'subtotal_before_discounts', 'discount_total',
    'subtotal', 'shipping_total', 'tax_total', 'grand_total', 'placed_at', 'cancelled_at',
])]
class Order extends Model
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

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_session_id');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class, 'payment_status' => OrderPaymentStatus::class,
            'fulfillment_status' => OrderFulfillmentStatus::class,
            'subtotal_before_discounts' => 'decimal:4', 'discount_total' => 'decimal:4',
            'subtotal' => 'decimal:4', 'shipping_total' => 'decimal:4', 'tax_total' => 'decimal:4',
            'grand_total' => 'decimal:4', 'placed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',
        ];
    }
}
