<?php

namespace App\Modules\Fiscal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fiscal\Application\Contracts\FiscalArtifactStorage;
use App\Modules\Fiscal\Application\GenerateFiscalDocument;
use App\Modules\Fiscal\Application\SubmitFiscalDocumentToSunat;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentType;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use App\Modules\Fiscal\Domain\Models\FiscalDocumentItem;
use App\Modules\Fiscal\Domain\Models\FiscalSubmission;
use App\Modules\Fiscal\Http\Requests\GenerateFiscalDocumentRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiscalDocumentController extends Controller
{
    public function index(Request $request, TenantContext $tenants, StoreContext $stores): Response
    {
        $search = trim((string) $request->query('search'));
        $type = $this->enumFilter((string) $request->query('type'), FiscalDocumentType::cases());
        $status = $this->enumFilter((string) $request->query('status'), FiscalDocumentStatus::cases());
        $from = $request->date('from')?->toDateString();
        $until = $request->date('until')?->toDateString();
        $documents = $stores->get()->fiscalDocuments()->where('tenant_id', $tenants->id())
            ->with('order:id,order_number')->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
            ->where('full_number', 'ilike', "%{$search}%")
            ->orWhere('customer_name', 'ilike', "%{$search}%")
            ->orWhere('customer_document_number', 'ilike', "%{$search}%")
            ->orWhereHas('order', fn ($orders) => $orders->where('order_number', 'ilike', "%{$search}%"))))
            ->when($type !== '', fn ($query) => $query->where('document_type', $type))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($from !== null, fn ($query) => $query->whereDate('issue_date', '>=', $from))
            ->when($until !== null, fn ($query) => $query->whereDate('issue_date', '<=', $until))
            ->orderByDesc('issue_date')->orderByDesc('created_at')->paginate(20)->withQueryString();
        $documents->through(fn (FiscalDocument $document): array => [
            'id' => $document->getKey(), 'full_number' => $document->full_number,
            'document_type' => $document->document_type->value, 'status' => $document->status->value,
            'order_number' => $document->order->order_number, 'customer_name' => $document->customer_name,
            'currency' => $document->currency, 'grand_total' => $document->grand_total,
            'issue_date' => $document->issue_date->toDateString(),
        ]);

        return Inertia::render('admin/fiscal-documents/Index', [
            'documents' => $documents,
            'filters' => ['search' => $search, 'type' => $type, 'status' => $status, 'from' => $from, 'until' => $until],
            'documentTypes' => array_column(FiscalDocumentType::cases(), 'value'),
            'statuses' => array_column(FiscalDocumentStatus::cases(), 'value'),
        ]);
    }

    public function show(Request $request, TenantContext $tenants, StoreContext $stores, string $fiscalDocument): Response
    {
        $document = $stores->get()->fiscalDocuments()->where('tenant_id', $tenants->id())
            ->with(['order:id,order_number', 'items', 'submissions', 'creditNotes:id,related_fiscal_document_id,full_number,status', 'generatedBy:id,name'])
            ->findOrFail($fiscalDocument);

        return Inertia::render('admin/fiscal-documents/Show', [
            'document' => $this->serialize($document),
            'sunat' => [
                'environment' => config('sunat.environment'),
                'can_manage' => $request->user()->can('fiscal_documents.manage'),
            ],
        ]);
    }

    public function submitSunat(
        TenantContext $tenants,
        StoreContext $stores,
        SubmitFiscalDocumentToSunat $action,
        string $fiscalDocument,
    ): RedirectResponse {
        $document = $stores->get()->fiscalDocuments()->where('tenant_id', $tenants->id())->findOrFail($fiscalDocument);
        $submission = $action->handle($tenants->id(), (string) $stores->id(), (string) $document->getKey());

        return back()->with('status', "SUNAT submission attempt {$submission->attempt_number}: {$submission->status->value}.");
    }

    public function downloadArtifact(
        TenantContext $tenants,
        StoreContext $stores,
        FiscalArtifactStorage $artifacts,
        string $fiscalDocument,
        string $submission,
        string $artifact,
    ): StreamedResponse {
        $document = $stores->get()->fiscalDocuments()->where('tenant_id', $tenants->id())->findOrFail($fiscalDocument);
        $attempt = $document->submissions()->where('tenant_id', $tenants->id())->findOrFail($submission);
        [$key, $extension] = match ($artifact) {
            'signed-xml' => [$attempt->signed_xml_storage_key, 'xml'],
            'cdr' => [$attempt->cdr_storage_key, 'zip'],
            default => abort(404),
        };
        abort_if($key === null, 404);
        $contents = $artifacts->get($tenants->id(), $key);

        return response()->streamDownload(static function () use ($contents): void {
            echo $contents;
        }, "{$artifact}-{$document->full_number}.{$extension}", [
            'Content-Type' => $extension === 'xml' ? 'application/xml' : 'application/zip',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function store(
        GenerateFiscalDocumentRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        GenerateFiscalDocument $action,
        string $order,
    ): RedirectResponse {
        $scopedOrder = $stores->get()->orders()->where('tenant_id', $tenants->id())->findOrFail($order);
        $document = $action->handle($tenants->get(), $stores->get(), $scopedOrder, $request->validated(), $request->user());

        return to_route('admin.fiscal-documents.show', $document)->with('status', "Fiscal document {$document->full_number} generated. No SUNAT submission was performed.");
    }

    private function serialize(FiscalDocument $document): array
    {
        return [
            'id' => $document->getKey(), 'full_number' => $document->full_number,
            'document_type' => $document->document_type->value, 'status' => $document->status->value,
            'currency' => $document->currency, 'issue_date' => $document->issue_date->toDateString(),
            'generated_at' => $document->generated_at->toISOString(), 'generated_by' => $document->generatedBy->name,
            'order' => ['id' => $document->order_id, 'order_number' => $document->order->order_number],
            'issuer' => [
                'country_code' => $document->issuer_country_code, 'tax_id' => $document->issuer_tax_id,
                'legal_name' => $document->issuer_legal_name, 'trade_name' => $document->issuer_trade_name,
                'address_line_1' => $document->issuer_address_line_1, 'address_line_2' => $document->issuer_address_line_2,
                'district' => $document->issuer_district, 'province' => $document->issuer_province,
                'region' => $document->issuer_region, 'postal_code' => $document->issuer_postal_code,
                'ubigeo' => $document->issuer_ubigeo,
            ],
            'customer' => [
                'document_type' => $document->customer_document_type,
                'document_number' => $document->customer_document_number,
                'name' => $document->customer_name, 'address' => $document->customer_address,
            ],
            'totals' => [
                'taxable_amount' => $document->taxable_amount, 'exempt_amount' => $document->exempt_amount,
                'unaffected_amount' => $document->unaffected_amount, 'tax_amount' => $document->tax_amount,
                'discount_total' => $document->discount_total, 'subtotal' => $document->subtotal,
                'grand_total' => $document->grand_total,
            ],
            'items' => $document->items->map(fn (FiscalDocumentItem $item): array => [
                'id' => $item->getKey(), 'description' => $item->description, 'sku' => $item->sku,
                'quantity' => $item->quantity, 'unit_price' => $item->unit_price,
                'base_amount' => $item->base_amount, 'discount_amount' => $item->discount_amount,
                'tax_category_code' => $item->tax_category_code->value, 'tax_rate' => $item->tax_rate,
                'taxable_amount' => $item->taxable_amount, 'exempt_amount' => $item->exempt_amount,
                'unaffected_amount' => $item->unaffected_amount,
                'tax_amount' => $item->tax_amount, 'line_total' => $item->line_total,
            ])->values(),
            'submissions' => $document->submissions->map(fn (FiscalSubmission $submission): array => [
                'id' => $submission->getKey(), 'attempt_number' => $submission->attempt_number,
                'status' => $submission->status->value, 'provider' => $submission->provider,
                'response_code' => $submission->response_code, 'response_message' => $submission->response_message,
                'submitted_at' => $submission->submitted_at?->toISOString(),
                'responded_at' => $submission->responded_at?->toISOString(),
                'environment' => $submission->environment,
                'observations' => $submission->observations ?? [],
                'signed_xml_available' => $submission->signed_xml_storage_key !== null,
                'cdr_available' => $submission->cdr_storage_key !== null,
            ])->values(),
            'credit_notes' => $document->creditNotes->map(fn (FiscalDocument $note): array => [
                'id' => $note->getKey(), 'full_number' => $note->full_number, 'status' => $note->status->value,
            ])->values(),
        ];
    }

    private function enumFilter(string $value, array $cases): string
    {
        $value = trim($value);

        return collect($cases)->contains(fn ($case): bool => $case->value === $value) ? $value : '';
    }
}
