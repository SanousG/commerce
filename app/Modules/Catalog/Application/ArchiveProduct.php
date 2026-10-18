<?php

namespace App\Modules\Catalog\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class ArchiveProduct
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(Tenant $tenant, Product $product, ?User $actor = null): Product
    {
        return DB::transaction(function () use ($tenant, $product, $actor): Product {
            $scopedProduct = $tenant->products()->lockForUpdate()->findOrFail($product->getKey());
            if ($scopedProduct->status === ProductStatus::Archived) {
                return $scopedProduct;
            }

            $from = $scopedProduct->status;
            $scopedProduct->update(['status' => ProductStatus::Archived]);
            $this->audit->record(
                $tenant,
                $actor === null ? AuditActorType::System : AuditActorType::User,
                AuditAction::ProductArchived,
                AuditSubjectType::Product,
                $scopedProduct->getKey(),
                ['changes' => ['status' => ['from' => $from, 'to' => ProductStatus::Archived]]],
                actor: $actor,
                subjectLabel: $scopedProduct->name,
            );

            return $scopedProduct;
        });
    }
}
