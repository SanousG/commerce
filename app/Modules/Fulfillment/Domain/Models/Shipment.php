<?php

namespace App\Modules\Fulfillment\Domain\Models;

use App\Models\User;
use App\Modules\Fulfillment\Domain\Enums\ShipmentStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'store_id', 'order_id', 'fulfillment_id', 'status', 'carrier_name', 'service_name',
    'tracking_number', 'tracking_url',
    'created_by_user_id', 'ready_at', 'shipped_at', 'delivered_at', 'cancelled_at',
])]
class Shipment extends Model
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

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'ready_at' => 'immutable_datetime',
            'shipped_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
