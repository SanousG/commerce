<?php

namespace App\Modules\Payments\Domain\Models;

use App\Modules\Payments\Domain\Enums\CodDepositType;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'store_id', 'bank_transfer_enabled', 'cash_enabled', 'online_enabled', 'cod_enabled',
    'cod_deposit_type', 'cod_deposit_value',
])]
class StorePaymentSettings extends Model
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

    protected function casts(): array
    {
        return [
            'bank_transfer_enabled' => 'boolean', 'cash_enabled' => 'boolean', 'online_enabled' => 'boolean',
            'cod_enabled' => 'boolean', 'cod_deposit_type' => CodDepositType::class,
            'cod_deposit_value' => 'decimal:4',
        ];
    }
}
