<?php

namespace App\Modules\Fiscal\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Fiscal\Domain\Enums\FiscalConfigurationStatus;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentType;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use App\Modules\Fiscal\Domain\Models\FiscalIssuerProfile;
use App\Modules\Fiscal\Domain\Models\FiscalSeries;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerateFiscalDocument
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array{document_type: string, customer_document_type?: ?string, customer_document_number?: ?string, customer_name?: ?string, customer_address?: ?string}  $input
     */
    public function handle(Tenant $tenant, Store $store, Order $order, array $input, User $actor): FiscalDocument
    {
        return DB::transaction(function () use ($tenant, $store, $order, $input, $actor): FiscalDocument {
            $lockedOrder = Order::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->lockForUpdate()->findOrFail($order->getKey());
            $type = FiscalDocumentType::from($input['document_type']);

            if (! in_array($type, [FiscalDocumentType::Invoice, FiscalDocumentType::Receipt], true)) {
                throw ValidationException::withMessages(['document_type' => 'Credit note issuance is not available in this phase.']);
            }

            $existing = FiscalDocument::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->where('order_id', $lockedOrder->getKey())
                ->whereIn('document_type', [FiscalDocumentType::Invoice->value, FiscalDocumentType::Receipt->value])
                ->first();
            if ($existing !== null) {
                $this->assertIdempotent($existing, $type, $input);

                return $existing;
            }

            if ($lockedOrder->status !== OrderStatus::Confirmed) {
                throw ValidationException::withMessages(['order' => 'Only confirmed orders can receive a fiscal document.']);
            }
            if ($lockedOrder->tax_snapshot_version !== 1) {
                throw ValidationException::withMessages(['order' => 'ORDER_TAX_SNAPSHOT_MISSING']);
            }
            if (BigDecimal::of($lockedOrder->shipping_total)->isGreaterThan(0)) {
                throw ValidationException::withMessages(['order' => 'Shipping tax is not defined in this phase.']);
            }

            $profile = FiscalIssuerProfile::query()->where('tenant_id', $tenant->getKey())
                ->where('status', FiscalConfigurationStatus::Active->value)->first();
            if ($profile === null) {
                throw ValidationException::withMessages(['issuer' => 'An active fiscal issuer profile is required.']);
            }

            $customer = $this->customerSnapshot($type, $lockedOrder, $input);
            $issueDate = CarbonImmutable::now($store->timezone)->startOfDay();
            $series = FiscalSeries::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->where('document_type', $type->value)
                ->where('status', FiscalConfigurationStatus::Active->value)->lockForUpdate()->first();
            if ($series === null) {
                throw ValidationException::withMessages(['series' => 'An active series for this store and document type is required.']);
            }

            $items = $lockedOrder->items()->orderBy('created_at')->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['order' => 'The order has no items to issue.']);
            }

            $totals = [
                'taxable_amount' => BigDecimal::zero(), 'exempt_amount' => BigDecimal::zero(),
                'unaffected_amount' => BigDecimal::zero(), 'tax_amount' => BigDecimal::zero(),
            ];
            $snapshots = [];
            foreach ($items as $position => $item) {
                if ($item->tax_category_code === null || $item->tax_rate === null
                    || $item->taxable_amount === null || $item->exempt_amount === null
                    || $item->unaffected_amount === null || $item->tax_amount === null) {
                    throw ValidationException::withMessages(['order' => 'ORDER_TAX_SNAPSHOT_MISSING']);
                }
                $totals['taxable_amount'] = $totals['taxable_amount']->plus($item->taxable_amount);
                $totals['exempt_amount'] = $totals['exempt_amount']->plus($item->exempt_amount);
                $totals['unaffected_amount'] = $totals['unaffected_amount']->plus($item->unaffected_amount);
                $totals['tax_amount'] = $totals['tax_amount']->plus($item->tax_amount);
                $baseAmount = BigDecimal::of($item->taxable_amount)->plus($item->exempt_amount)
                    ->plus($item->unaffected_amount)->toScale(4);
                $snapshots[] = [
                    'tenant_id' => $tenant->getKey(), 'order_id' => $lockedOrder->getKey(),
                    'order_item_id' => $item->getKey(), 'position' => $position + 1,
                    'description' => trim($item->product_name.($item->variant_name ? ' - '.$item->variant_name : '')),
                    'sku' => $item->sku, 'quantity' => number_format($item->quantity, 4, '.', ''),
                    'unit_price' => $item->base_unit_price, 'base_amount' => (string) $baseAmount,
                    'discount_amount' => $item->line_discount_total, 'tax_category_code' => $item->tax_category_code,
                    'tax_rate' => $item->tax_rate, 'taxable_amount' => $item->taxable_amount,
                    'exempt_amount' => $item->exempt_amount, 'unaffected_amount' => $item->unaffected_amount,
                    'tax_amount' => $item->tax_amount, 'line_total' => $item->line_total,
                ];
            }

            $subtotal = collect($snapshots)->reduce(
                fn (BigDecimal $sum, array $item): BigDecimal => $sum->plus($item['line_total']),
                BigDecimal::zero(),
            )->toScale(4);
            $discountTotal = collect($snapshots)->reduce(
                fn (BigDecimal $sum, array $item): BigDecimal => $sum->plus($item['discount_amount']),
                BigDecimal::zero(),
            )->toScale(4);
            if (! $subtotal->isEqualTo(BigDecimal::of($lockedOrder->grand_total))) {
                throw ValidationException::withMessages(['order' => 'The order item snapshots do not reconcile with its grand total.']);
            }
            if (! $discountTotal->isEqualTo(BigDecimal::of($lockedOrder->discount_total))) {
                throw ValidationException::withMessages(['order' => 'The order item discounts do not reconcile with its discount total.']);
            }
            foreach (['taxable_amount', 'exempt_amount', 'unaffected_amount'] as $field) {
                if (! $totals[$field]->toScale(4)->isEqualTo(BigDecimal::of($lockedOrder->{$field}))) {
                    throw ValidationException::withMessages(['order' => 'The order item tax snapshots do not reconcile with order totals.']);
                }
            }
            if (! $totals['tax_amount']->toScale(4)->isEqualTo(BigDecimal::of($lockedOrder->tax_total))) {
                throw ValidationException::withMessages(['order' => 'The order item tax snapshots do not reconcile with order totals.']);
            }

            $sequence = $series->next_number;
            $fullNumber = $series->series.'-'.str_pad((string) $sequence, $series->number_padding, '0', STR_PAD_LEFT);
            $document = FiscalDocument::query()->create([
                'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), 'order_id' => $lockedOrder->getKey(),
                'fiscal_issuer_profile_id' => $profile->getKey(), 'fiscal_series_id' => $series->getKey(),
                'generated_by_user_id' => $actor->getKey(),
                'document_type' => $type->value, 'status' => FiscalDocumentStatus::Generated->value,
                'series' => $series->series, 'sequence_number' => $sequence, 'full_number' => $fullNumber,
                'currency' => $lockedOrder->currency, 'issue_date' => $issueDate->toDateString(),
                'issuer_country_code' => $profile->country_code, 'issuer_tax_id' => $profile->tax_id,
                'issuer_legal_name' => $profile->legal_name, 'issuer_trade_name' => $profile->trade_name,
                'issuer_address_line_1' => $profile->fiscal_address_line_1,
                'issuer_address_line_2' => $profile->fiscal_address_line_2, 'issuer_district' => $profile->district,
                'issuer_province' => $profile->province, 'issuer_region' => $profile->region,
                'issuer_postal_code' => $profile->postal_code, 'issuer_ubigeo' => $profile->ubigeo,
                ...$customer,
                'taxable_amount' => $lockedOrder->taxable_amount, 'exempt_amount' => $lockedOrder->exempt_amount,
                'unaffected_amount' => $lockedOrder->unaffected_amount, 'tax_amount' => $lockedOrder->tax_total,
                'discount_total' => $lockedOrder->discount_total, 'subtotal' => $lockedOrder->subtotal,
                'grand_total' => $lockedOrder->grand_total, 'generated_at' => now(),
            ]);
            $document->items()->createMany($snapshots);
            $series->update(['next_number' => $sequence + 1]);
            $this->audit->record(
                $tenant,
                AuditActorType::User,
                AuditAction::FiscalDocumentIssued,
                AuditSubjectType::FiscalDocument,
                (string) $document->getKey(),
                ['status' => $document->status->value],
                $store,
                $actor,
                idempotencyKey: 'fiscal.generated:'.$document->getKey(),
                subjectLabel: $document->full_number,
            );

            return $document->load('items');
        }, 3);
    }

    private function customerSnapshot(FiscalDocumentType $type, Order $order, array $input): array
    {
        $documentType = $input['customer_document_type'] ?? null;
        $documentNumber = $input['customer_document_number'] ?? null;
        $name = $input['customer_name'] ?? $order->customer_name;
        $address = $input['customer_address'] ?? null;

        if ($type === FiscalDocumentType::Invoice
            && ($documentType !== 'RUC' || $documentNumber === null || $name === null || $address === null)) {
            throw ValidationException::withMessages([
                'customer_document_type' => 'Factura requires RUC, legal name, and fiscal address.',
            ]);
        }
        if (($documentType === null) !== ($documentNumber === null)) {
            throw ValidationException::withMessages(['customer_document_number' => 'Document type and number must be provided together.']);
        }

        return [
            'customer_document_type' => $documentType, 'customer_document_number' => $documentNumber,
            'customer_name' => $name ?: 'Consumidor final', 'customer_address' => $address,
        ];
    }

    private function assertIdempotent(FiscalDocument $existing, FiscalDocumentType $type, array $input): void
    {
        $expected = [
            $type->value, $input['customer_document_type'] ?? null, $input['customer_document_number'] ?? null,
            $input['customer_name'] ?? $existing->customer_name, $input['customer_address'] ?? null,
        ];
        $actual = [
            $existing->document_type->value, $existing->customer_document_type, $existing->customer_document_number,
            $existing->customer_name, $existing->customer_address,
        ];
        if ($expected !== $actual) {
            throw ValidationException::withMessages([
                'order' => 'This order already has a primary fiscal document with a different fiscal snapshot.',
            ]);
        }
    }
}
