<?php

namespace App\Modules\Tax\Domain\Models;

use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Tax\Domain\Enums\TaxCategoryCode;
use App\Modules\Tax\Domain\Enums\TaxCategoryStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'code', 'name', 'status'])]
class TaxCategory extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected function casts(): array
    {
        return [
            'code' => TaxCategoryCode::class,
            'status' => TaxCategoryStatus::class,
        ];
    }
}
