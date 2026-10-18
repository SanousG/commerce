<?php

namespace App\Modules\Catalog\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateProduct
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array{name: string, description?: ?string, status: string, tax_category_id?: ?string, categories?: list<string>} $attributes */
    public function handle(Tenant $tenant, Product $product, array $attributes, ?User $actor = null): Product
    {
        return DB::transaction(function () use ($tenant, $product, $attributes, $actor): Product {
            $lockedProduct = $tenant->products()->lockForUpdate()->findOrFail($product->getKey());
            $categoryIds = $attributes['categories'] ?? [];

            if ($tenant->categories()->whereKey($categoryIds)->count() !== count(array_unique($categoryIds))) {
                throw ValidationException::withMessages(['categories' => 'Every category must belong to the current company.']);
            }

            $currentCategoryIds = $lockedProduct->categories()->pluck('categories.id')->sort()->values()->all();
            $newCategoryIds = collect($categoryIds)->sort()->values()->all();
            $categoriesChanged = $currentCategoryIds !== $newCategoryIds;

            $lockedProduct->fill([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'status' => $attributes['status'],
                ...array_key_exists('tax_category_id', $attributes)
                    ? ['tax_category_id' => $attributes['tax_category_id']]
                    : [],
            ]);
            $dirty = $lockedProduct->getDirty();
            unset($dirty['updated_at']);

            if ($dirty === [] && ! $categoriesChanged) {
                return $lockedProduct;
            }

            $changes = [];
            foreach (array_keys($dirty) as $field) {
                $changes[$field] = $field === 'description'
                    ? ['changed' => true]
                    : [
                        'from' => $lockedProduct->getRawOriginal($field),
                        'to' => $lockedProduct->getAttribute($field),
                    ];
            }
            if ($categoriesChanged) {
                $changes['categories'] = ['from' => $currentCategoryIds, 'to' => $newCategoryIds];
            }

            if ($dirty !== []) {
                $lockedProduct->save();
            }
            if ($categoriesChanged) {
                $lockedProduct->categories()->sync(collect($categoryIds)->mapWithKeys(
                    fn (string $id): array => [$id => ['tenant_id' => $tenant->getKey()]],
                )->all());
            }

            $this->audit->record(
                $tenant,
                $actor === null ? AuditActorType::System : AuditActorType::User,
                AuditAction::ProductUpdated,
                AuditSubjectType::Product,
                $lockedProduct->getKey(),
                ['changes' => $changes],
                actor: $actor,
                subjectLabel: $lockedProduct->name,
            );

            return $lockedProduct;
        });
    }
}
