<?php

namespace App\Modules\Fiscal\Domain\Models;

use App\Models\User;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentType;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'store_id', 'order_id', 'fiscal_issuer_profile_id', 'fiscal_series_id',
    'related_fiscal_document_id', 'generated_by_user_id', 'document_type', 'status', 'series', 'sequence_number', 'full_number',
    'currency', 'issue_date', 'issuer_country_code', 'issuer_tax_id', 'issuer_legal_name', 'issuer_trade_name',
    'issuer_address_line_1', 'issuer_address_line_2', 'issuer_district', 'issuer_province', 'issuer_region',
    'issuer_postal_code', 'issuer_ubigeo', 'customer_document_type', 'customer_document_number',
    'customer_name', 'customer_address', 'taxable_amount', 'exempt_amount', 'unaffected_amount',
    'tax_amount', 'discount_total', 'subtotal', 'grand_total', 'credit_note_reason_code',
    'credit_note_reason', 'adjustment_amount', 'generated_at', 'submitted_at', 'accepted_at',
    'rejected_at', 'cancelled_at',
])]
class FiscalDocument extends Model
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function issuerProfile(): BelongsTo
    {
        return $this->belongsTo(FiscalIssuerProfile::class, 'fiscal_issuer_profile_id');
    }

    public function fiscalSeries(): BelongsTo
    {
        return $this->belongsTo(FiscalSeries::class);
    }

    public function relatedDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_fiscal_document_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(self::class, 'related_fiscal_document_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FiscalDocumentItem::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FiscalSubmission::class);
    }

    protected function casts(): array
    {
        return [
            'document_type' => FiscalDocumentType::class,
            'status' => FiscalDocumentStatus::class,
            'sequence_number' => 'integer',
            'issue_date' => 'immutable_date',
            'taxable_amount' => 'decimal:4',
            'exempt_amount' => 'decimal:4',
            'unaffected_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'adjustment_amount' => 'decimal:4',
            'generated_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
