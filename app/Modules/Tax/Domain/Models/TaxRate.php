<?php

namespace App\Modules\Tax\Domain\Models;

use App\Modules\Tax\Domain\Enums\TaxCategoryStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'tax_category_id', 'name', 'rate', 'valid_from', 'valid_until', 'status'])]
class TaxRate extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'valid_from' => 'immutable_date',
            'valid_until' => 'immutable_date',
            'status' => TaxCategoryStatus::class,
        ];
    }
}
