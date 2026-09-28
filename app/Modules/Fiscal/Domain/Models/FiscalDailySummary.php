<?php

namespace App\Modules\Fiscal\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'store_id', 'summary_date', 'sequence', 'status', 'identifier', 'environment', 'ticket', 'submitted_at', 'responded_at'])]
final class FiscalDailySummary extends Model
{
    use HasUuids;

    public function items(): HasMany
    {
        return $this->hasMany(FiscalDailySummaryItem::class);
    }

    protected function casts(): array
    {
        return [
            'summary_date' => 'immutable_date',
            'sequence' => 'integer',
            'submitted_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
        ];
    }
}
