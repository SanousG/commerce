<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\ManageProductMedia;
use App\Modules\Catalog\Http\Requests\AttachVariantMediaRequest;
use App\Modules\Catalog\Http\Requests\ReorderVariantMediaRequest;
use App\Modules\Media\Domain\Enums\MediaStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;

class ProductVariantMediaController extends Controller
{
    public function store(AttachVariantMediaRequest $request, TenantContext $tenants, ManageProductMedia $media, string $variant): RedirectResponse
    {
        $tenant = $tenants->get();
        $scopedVariant = $tenant->productVariants()->findOrFail($variant);
        $asset = $tenant->mediaAssets()->where('status', MediaStatus::Ready)->findOrFail($request->validated('media_asset_id'));
        $media->attachVariant($tenant, $scopedVariant, $asset);

        return back()->with('status', 'Image attached to variant.');
    }

    public function destroy(TenantContext $tenants, ManageProductMedia $media, string $variant, string $mediaAsset): RedirectResponse
    {
        $tenant = $tenants->get();
        $scopedVariant = $tenant->productVariants()->findOrFail($variant);
        $asset = $tenant->mediaAssets()->where('status', MediaStatus::Ready)->findOrFail($mediaAsset);
        abort_unless($scopedVariant->media()->where('media_asset_id', $asset->getKey())->exists(), 404);
        $media->detachVariant($scopedVariant, $asset);

        return back()->with('status', 'Image removed from variant.');
    }

    public function reorder(ReorderVariantMediaRequest $request, TenantContext $tenants, ManageProductMedia $media, string $variant): RedirectResponse
    {
        $scopedVariant = $tenants->get()->productVariants()->findOrFail($variant);
        $media->reorderVariant($scopedVariant, $request->validated('media_asset_ids'));

        return back()->with('status', 'Variant images reordered.');
    }
}
