<?php

namespace App\Modules\Fiscal\Domain\Models;

use App\Modules\Fiscal\Domain\Enums\FiscalConfigurationStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'country_code', 'tax_id', 'legal_name', 'trade_name', 'fiscal_address_line_1',
    'fiscal_address_line_2', 'district', 'province', 'region', 'postal_code', 'ubigeo', 'status',
])]
class FiscalIssuerProfile extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FiscalDocument::class);
    }

    protected function casts(): array
    {
        return ['status' => FiscalConfigurationStatus::class];
    }
}
