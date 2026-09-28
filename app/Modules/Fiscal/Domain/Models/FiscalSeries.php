<?php

namespace App\Modules\Fiscal\Domain\Models;

use App\Modules\Fiscal\Domain\Enums\FiscalConfigurationStatus;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentType;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'store_id', 'document_type', 'series', 'number_padding', 'next_number', 'status'])]
class FiscalSeries extends Model
{
    use HasUuids;

    protected $table = 'fiscal_series';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FiscalDocument::class);
    }

    protected function casts(): array
    {
        return [
            'document_type' => FiscalDocumentType::class,
            'next_number' => 'integer',
            'number_padding' => 'integer',
            'status' => FiscalConfigurationStatus::class,
        ];
    }
}
