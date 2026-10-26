<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\ManageProductMedia;
use App\Modules\Catalog\Http\Requests\AttachProductMediaRequest;
use App\Modules\Catalog\Http\Requests\ReorderProductMediaRequest;
use App\Modules\Media\Domain\Enums\MediaStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;

class ProductMediaController extends Controller
{
    public function store(AttachProductMediaRequest $request, TenantContext $tenants, ManageProductMedia $media, string $product): RedirectResponse
    {
        $tenant = $tenants->get();
        $scopedProduct = $tenant->products()->findOrFail($product);
        $asset = $tenant->mediaAssets()->where('status', MediaStatus::Ready)->findOrFail($request->validated('media_asset_id'));
        $media->attachProduct($tenant, $scopedProduct, $asset, $request->boolean('is_primary'), $request->validated('alt_text'));

        return back()->with('status', __('Image attached to product.'));
    }

    public function destroy(TenantContext $tenants, ManageProductMedia $media, string $product, string $mediaAsset): RedirectResponse
    {
        $tenant = $tenants->get();
        $scopedProduct = $tenant->products()->findOrFail($product);
        $asset = $tenant->mediaAssets()->where('status', MediaStatus::Ready)->findOrFail($mediaAsset);
        abort_unless($scopedProduct->media()->where('media_asset_id', $asset->getKey())->exists(), 404);
        $media->detachProduct($scopedProduct, $asset);

        return back()->with('status', __('Image removed from product.'));
    }

    public function reorder(ReorderProductMediaRequest $request, TenantContext $tenants, ManageProductMedia $media, string $product): RedirectResponse
    {
        $scopedProduct = $tenants->get()->products()->findOrFail($product);
        $media->reorderProduct($scopedProduct, $request->validated('media_asset_ids'), $request->validated('primary_media_asset_id'));

        return back()->with('status', __('Product gallery updated.'));
    }
}
