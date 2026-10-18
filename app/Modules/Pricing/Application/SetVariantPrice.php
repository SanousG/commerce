<?php

namespace App\Modules\Pricing\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Pricing\Domain\Models\VariantPrice;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetVariantPrice
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(
        Tenant $tenant,
        PriceList $priceList,
        ProductVariant $variant,
        string $amount,
        ?User $actor = null,
    ): VariantPrice {
        return DB::transaction(function () use ($tenant, $priceList, $variant, $amount, $actor): VariantPrice {
            $scopedList = $tenant->priceLists()->lockForUpdate()->findOrFail($priceList->getKey());
            $scopedVariant = ProductVariant::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($variant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($scopedList->status === PriceListStatus::Archived) {
                throw ValidationException::withMessages(['amount' => 'Archived price lists cannot be changed.']);
            }

            $amount = $this->normalizeAmount($amount);
            $price = VariantPrice::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('price_list_id', $scopedList->getKey())
                ->where('product_variant_id', $scopedVariant->getKey())
                ->lockForUpdate()
                ->first();
            $fromAmount = $price?->amount;

            if ($price !== null && $fromAmount === $amount) {
                return $price;
            }

            if ($price === null) {
                $price = VariantPrice::query()->create([
                    'tenant_id' => $tenant->getKey(),
                    'price_list_id' => $scopedList->getKey(),
                    'product_variant_id' => $scopedVariant->getKey(),
                    'amount' => $amount,
                ]);
            } else {
                $price->update(['amount' => $amount]);
            }

            $this->audit->record(
                $tenant,
                $actor === null ? AuditActorType::System : AuditActorType::User,
                AuditAction::PriceUpdated,
                AuditSubjectType::ProductVariant,
                $scopedVariant->getKey(),
                [
                    'price_list_id' => $scopedList->getKey(),
                    'from_amount' => $fromAmount,
                    'to_amount' => $amount,
                    'currency' => $scopedList->currency,
                ],
                actor: $actor,
                subjectLabel: $scopedVariant->sku,
            );

            return $price;
        });
    }

    public function remove(Tenant $tenant, PriceList $priceList, ProductVariant $variant): void
    {
        DB::transaction(function () use ($tenant, $priceList, $variant): void {
            $scopedList = $tenant->priceLists()->lockForUpdate()->findOrFail($priceList->getKey());
            $scopedVariant = ProductVariant::query()->where('tenant_id', $tenant->getKey())
                ->whereKey($variant->getKey())->firstOrFail();

            if ($scopedList->status === PriceListStatus::Archived) {
                throw ValidationException::withMessages(['amount' => 'Archived price lists cannot be changed.']);
            }

            VariantPrice::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('price_list_id', $scopedList->getKey())
                ->where('product_variant_id', $scopedVariant->getKey())
                ->delete();
        });
    }

    private function normalizeAmount(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', trim($amount), 2), 2, '');

        return (ltrim($whole, '0') ?: '0').'.'.str_pad($fraction, 4, '0');
    }
}
