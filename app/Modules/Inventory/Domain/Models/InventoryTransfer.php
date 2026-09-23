<?php

namespace App\Modules\Inventory\Domain\Models;

use App\Models\User;
use App\Modules\Inventory\Domain\Enums\InventoryTransferStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'source_location_id', 'destination_location_id', 'status',
    'created_by', 'notes', 'completed_at',
])]
class InventoryTransfer extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'destination_location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryTransferLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['status' => InventoryTransferStatus::class, 'completed_at' => 'immutable_datetime'];
    }
}
