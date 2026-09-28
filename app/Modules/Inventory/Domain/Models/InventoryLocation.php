<?php

namespace App\Modules\Inventory\Domain\Models;

use App\Modules\Fulfillment\Domain\Models\Fulfillment;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'name', 'code', 'status', 'is_default'])]
class InventoryLocation extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    protected function casts(): array
    {
        return ['status' => InventoryLocationStatus::class, 'is_default' => 'boolean'];
    }
}
