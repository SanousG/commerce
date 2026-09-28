<?php

namespace App\Modules\Procurement\Application;

use App\Models\User;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Enums\SupplierStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Procurement\Domain\Models\Supplier;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePurchaseOrder
{
    public function __construct(private readonly GeneratePurchaseOrderNumber $numbers) {}

    public function handle(Tenant $tenant, Supplier $supplier, string $currency, ?string $expectedAt = null, ?string $notes = null, ?User $actor = null): PurchaseOrder
    {
        return DB::transaction(function () use ($tenant, $supplier, $currency, $expectedAt, $notes, $actor): PurchaseOrder {
            $supplier = Supplier::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($supplier->getKey());
            if ($supplier->status === SupplierStatus::Archived) {
                throw ValidationException::withMessages(['supplier_id' => 'An archived supplier cannot be used on a purchase order.']);
            }

            return PurchaseOrder::query()->create([
                'tenant_id' => $tenant->getKey(), 'supplier_id' => $supplier->getKey(), 'number' => $this->numbers->handle($tenant),
                'status' => PurchaseOrderStatus::Draft, 'currency' => ProcurementValues::currency($currency),
                'expected_at' => $expectedAt, 'notes' => $notes, 'subtotal' => '0.0000', 'discount_total' => '0.0000',
                'shipping_total' => '0.0000', 'tax_total' => '0.0000', 'grand_total' => '0.0000',
                'created_by_user_id' => $actor?->getKey(),
            ]);
        });
    }
}
