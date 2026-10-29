<?php

namespace App\Modules\Orders\Domain\Models;

use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use App\Modules\Fulfillment\Domain\Models\Fulfillment;
use App\Modules\Fulfillment\Domain\Models\Shipment;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Orders\Domain\Enums\OrderFulfillmentStatus;
use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Payments\Domain\Models\OrderPaymentPlan;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id', 'store_id', 'checkout_session_id', 'cart_id', 'customer_id', 'order_number', 'status',
    'payment_status', 'fulfillment_status', 'currency', 'customer_name', 'email', 'phone',
    'shipping_recipient_name', 'shipping_phone', 'shipping_country_code', 'shipping_region', 'shipping_city',
    'shipping_district', 'shipping_address_line_1', 'shipping_address_line_2', 'shipping_postal_code',
    'shipping_reference', 'billing_recipient_name', 'billing_phone', 'billing_country_code', 'billing_region',
    'billing_city', 'billing_district', 'billing_address_line_1', 'billing_address_line_2',
    'billing_postal_code', 'billing_reference', 'delivery_type', 'subtotal_before_discounts', 'discount_total',
    'subtotal', 'shipping_total', 'tax_total', 'grand_total', 'tax_snapshot_version', 'taxable_amount',
    'exempt_amount', 'unaffected_amount', 'tax_calculated_at', 'placed_at', 'cancelled_at',
    'pickup_inventory_location_id', 'pickup_name', 'pickup_address_line_1', 'pickup_address_line_2',
    'pickup_district', 'pickup_province', 'pickup_department', 'pickup_country_code', 'pickup_postal_code',
    'pickup_instructions',
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

    public function fiscalDocuments(): HasMany
    {
        return $this->hasMany(FiscalDocument::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentPlan(): HasOne
    {
        return $this->hasOne(OrderPaymentPlan::class);
    }

    public function pickupInventoryLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'pickup_inventory_location_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class, 'payment_status' => OrderPaymentStatus::class,
            'fulfillment_status' => OrderFulfillmentStatus::class,
            'subtotal_before_discounts' => 'decimal:4', 'discount_total' => 'decimal:4',
            'subtotal' => 'decimal:4', 'shipping_total' => 'decimal:4', 'tax_total' => 'decimal:4',
            'grand_total' => 'decimal:4', 'tax_snapshot_version' => 'integer', 'taxable_amount' => 'decimal:4',
            'exempt_amount' => 'decimal:4', 'unaffected_amount' => 'decimal:4',
            'tax_calculated_at' => 'immutable_datetime', 'placed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
