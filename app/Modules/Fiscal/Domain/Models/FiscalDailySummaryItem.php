<?php

namespace App\Modules\Fiscal\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'store_id', 'fiscal_daily_summary_id', 'fiscal_document_id', 'status'])]
final class FiscalDailySummaryItem extends Model
{
    use HasUuids;

    public function summary(): BelongsTo
    {
        return $this->belongsTo(FiscalDailySummary::class, 'fiscal_daily_summary_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class, 'fiscal_document_id');
    }
}
