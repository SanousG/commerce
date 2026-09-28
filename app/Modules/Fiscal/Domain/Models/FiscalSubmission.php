<?php

namespace App\Modules\Fiscal\Domain\Models;

use App\Modules\Fiscal\Domain\Enums\FiscalSubmissionStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'fiscal_document_id', 'attempt_number', 'status', 'provider', 'request_hash',
    'external_ticket', 'response_code', 'response_message', 'cdr_path', 'submitted_at', 'responded_at',
    'environment', 'request_storage_key', 'unsigned_xml_storage_key', 'signed_xml_storage_key',
    'zip_storage_key', 'cdr_storage_key', 'cdr_xml_storage_key', 'response_description',
    'observations', 'error_class', 'duration_ms',
])]
class FiscalSubmission extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class, 'fiscal_document_id');
    }

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'status' => FiscalSubmissionStatus::class,
            'submitted_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
            'observations' => 'array',
            'duration_ms' => 'integer',
        ];
    }
}
