<?php

namespace App\Modules\Fulfillment\Domain\Models;

use App\Models\User;
use App\Modules\Fulfillment\Domain\Enums\ShipmentStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['tenant_id', 'shipment_id', 'from_status', 'to_status', 'actor_user_id', 'reason'])]
class ShipmentStatusHistory extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Shipment status histories are append-only.'));
        static::deleting(fn () => throw new LogicException('Shipment status histories are append-only.'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return ['from_status' => ShipmentStatus::class, 'to_status' => ShipmentStatus::class];
    }
}
