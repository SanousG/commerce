<?php

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Domain\Enums\CategoryStatus;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveCategory
{
    /** @param array{name: string, status?: string, parent_id?: ?string, position?: int} $attributes */
    public function create(Tenant $tenant, array $attributes): Category
    {
        return DB::transaction(function () use ($tenant, $attributes): Category {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());

            return $lockedTenant->categories()->create([
                'name' => $attributes['name'],
                'slug' => $this->uniqueSlug($lockedTenant, $attributes['name']),
                'status' => CategoryStatus::Active,
                'parent_id' => $attributes['parent_id'] ?? null,
                'position' => $attributes['position'] ?? 0,
            ]);
        });
    }

    /** @param array{name: string, status: string, parent_id?: ?string, position?: int} $attributes */
    public function update(Tenant $tenant, Category $category, array $attributes): Category
    {
        return DB::transaction(function () use ($tenant, $category, $attributes): Category {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $scopedCategory = $lockedTenant->categories()->lockForUpdate()->findOrFail($category->getKey());
            $this->assertAcyclicParent($lockedTenant, $scopedCategory, $attributes['parent_id'] ?? null);
            $scopedCategory->update([
                'name' => $attributes['name'],
                'status' => $attributes['status'],
                'parent_id' => $attributes['parent_id'] ?? null,
                'position' => $attributes['position'] ?? 0,
            ]);

            return $scopedCategory;
        });
    }

    public function archive(Tenant $tenant, Category $category): Category
    {
        $scopedCategory = $tenant->categories()->findOrFail($category->getKey());
        $scopedCategory->update(['status' => CategoryStatus::Archived]);

        return $scopedCategory;
    }

    private function uniqueSlug(Tenant $tenant, string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while ($tenant->categories()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function assertAcyclicParent(Tenant $tenant, Category $category, ?string $parentId): void
    {
        $seen = [];

        while ($parentId !== null) {
            if ($parentId === $category->getKey() || isset($seen[$parentId])) {
                throw ValidationException::withMessages(['parent_id' => 'A category cannot be nested below itself.']);
            }

            $seen[$parentId] = true;
            $parentId = $tenant->categories()->whereKey($parentId)->value('parent_id');
        }
    }
}
