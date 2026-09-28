<?php

namespace App\Modules\Fulfillment\Domain\Models;

use App\Models\User;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
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
    'tenant_id', 'store_id', 'order_id', 'inventory_location_id', 'status', 'created_by_user_id',
    'started_at', 'fulfilled_at', 'cancelled_at', 'cancellation_reason',
])]
class Fulfillment extends Model
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FulfillmentItem::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(FulfillmentStatusHistory::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    protected function casts(): array
    {
        return [
            'status' => FulfillmentStatus::class,
            'started_at' => 'immutable_datetime',
            'fulfilled_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
