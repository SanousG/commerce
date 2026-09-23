<?php

namespace App\Modules\Checkout\Domain\Models;

use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Checkout\Domain\Enums\CheckoutDeliveryType;
use App\Modules\Checkout\Domain\Enums\CheckoutStatus;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id', 'store_id', 'cart_id', 'customer_id', 'token', 'idempotency_key', 'status', 'delivery_type',
    'currency', 'customer_name', 'email', 'phone', 'shipping_recipient_name', 'shipping_phone',
    'shipping_country_code', 'shipping_region', 'shipping_city', 'shipping_district', 'shipping_address_line_1',
    'shipping_address_line_2', 'shipping_postal_code', 'shipping_reference', 'billing_recipient_name',
    'billing_phone', 'billing_country_code', 'billing_region', 'billing_city', 'billing_district',
    'billing_address_line_1', 'billing_address_line_2', 'billing_postal_code', 'billing_reference',
    'subtotal_before_discounts', 'discount_total', 'subtotal', 'shipping_total', 'tax_total', 'grand_total',
    'expires_at', 'completed_at',
])]
class CheckoutSession extends Model
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

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CheckoutLine::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CheckoutStatus::class,
            'delivery_type' => CheckoutDeliveryType::class,
            'subtotal_before_discounts' => 'decimal:4', 'discount_total' => 'decimal:4', 'subtotal' => 'decimal:4',
            'shipping_total' => 'decimal:4', 'tax_total' => 'decimal:4', 'grand_total' => 'decimal:4',
            'expires_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime',
        ];
    }
}
