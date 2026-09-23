<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\CatalogAccess;
use App\Modules\Catalog\Application\SaveCategory;
use App\Modules\Catalog\Domain\Enums\CategoryStatus;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Http\Requests\CreateCategoryRequest;
use App\Modules\Catalog\Http\Requests\UpdateCategoryRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request, TenantContext $context, CatalogAccess $access): Response
    {
        abort_unless($access->canViewCategories($request->user()), 403);

        $categories = $context->get()->categories()
            ->with('parent:id,name')
            ->withCount('products')
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (Category $category): array => [
                'id' => $category->getKey(),
                'name' => $category->name,
                'slug' => $category->slug,
                'status' => $category->status->value,
                'position' => $category->position,
                'parent' => $category->parent?->name,
                'products_count' => $category->products_count,
            ]);

        return Inertia::render('admin/categories/Index', ['categories' => $categories]);
    }

    public function create(Request $request, TenantContext $context, CatalogAccess $access): Response
    {
        abort_unless($access->canCreateCategories($request->user()), 403);

        return Inertia::render('admin/categories/Create', [
            'parents' => $this->parents($context),
        ]);
    }

    public function store(CreateCategoryRequest $request, TenantContext $context, SaveCategory $action): RedirectResponse
    {
        $action->create($context->get(), $request->safe()->only(['name', 'parent_id', 'position']));

        return to_route('admin.categories.index')->with('status', 'Category created.');
    }

    public function edit(Request $request, TenantContext $context, CatalogAccess $access, string $category): Response
    {
        abort_unless($access->canViewCategories($request->user()), 403);
        $scopedCategory = $context->get()->categories()->findOrFail($category);

        return Inertia::render('admin/categories/Edit', [
            'category' => [
                'id' => $scopedCategory->getKey(),
                'name' => $scopedCategory->name,
                'slug' => $scopedCategory->slug,
                'status' => $scopedCategory->status->value,
                'parent_id' => $scopedCategory->parent_id,
                'position' => $scopedCategory->position,
            ],
            'parents' => $this->parents($context, $scopedCategory->getKey()),
            'statuses' => array_column(CategoryStatus::cases(), 'value'),
        ]);
    }

    public function update(
        UpdateCategoryRequest $request,
        TenantContext $context,
        SaveCategory $action,
        string $category,
    ): RedirectResponse {
        $scopedCategory = $context->get()->categories()->findOrFail($category);
        $action->update($context->get(), $scopedCategory, $request->safe()->only([
            'name', 'status', 'parent_id', 'position',
        ]));

        return back()->with('status', 'Category updated.');
    }

    public function archive(
        Request $request,
        TenantContext $context,
        CatalogAccess $access,
        SaveCategory $action,
        string $category,
    ): RedirectResponse {
        abort_unless($access->canArchiveCategories($request->user()), 403);
        $scopedCategory = $context->get()->categories()->findOrFail($category);
        $action->archive($context->get(), $scopedCategory);

        return to_route('admin.categories.index')->with('status', 'Category archived.');
    }

    private function parents(TenantContext $context, ?string $except = null): array
    {
        $categories = $context->get()->categories()
            ->where('status', '!=', CategoryStatus::Archived)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);
        $byId = $categories->keyBy(fn ($category) => $category->getKey());

        return $categories
            ->reject(function ($category) use ($except, $byId): bool {
                $candidateId = $category->getKey();
                $seen = [];

                while ($candidateId !== null && ! isset($seen[$candidateId])) {
                    if ($candidateId === $except) {
                        return true;
                    }

                    $seen[$candidateId] = true;
                    $candidateId = $byId->get($candidateId)?->parent_id;
                }

                return false;
            })
            ->map(fn ($category): array => ['id' => $category->getKey(), 'name' => $category->name])
            ->all();
    }
}
