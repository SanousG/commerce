<?php

namespace Tests\Feature\Media;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\ProductMedia;
use App\Modules\Catalog\Domain\Models\ProductVariantMedia;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\Storage;

class ProductMediaTest extends MediaFeatureTestCase
{
    public function test_product_gallery_primary_order_alt_override_and_reuse(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $productA = $this->createProduct($tenant);
        $productB = $this->createProduct($tenant);
        $assetA = $this->createAsset($tenant, $user, ['alt_text' => 'Black shoe']);
        $assetB = $this->createAsset($tenant, $user);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->post(route('admin.products.media.store', $productA), ['media_asset_id' => $assetA->getKey(), 'is_primary' => true, 'alt_text' => 'Black sport shoe side'])->assertRedirect();
        $this->post(route('admin.products.media.store', $productA), ['media_asset_id' => $assetB->getKey(), 'is_primary' => true])->assertRedirect();
        $this->post(route('admin.products.media.store', $productB), ['media_asset_id' => $assetA->getKey()])->assertRedirect();
        $this->assertSame(1, ProductMedia::query()->where('product_id', $productA->getKey())->where('is_primary', true)->count());
        $this->assertDatabaseHas('product_media', ['product_id' => $productA->getKey(), 'media_asset_id' => $assetA->getKey(), 'alt_text' => 'Black sport shoe side']);
        $this->assertDatabaseCount('media_assets', 2);

        $this->patch(route('admin.products.media.reorder', $productA), [
            'media_asset_ids' => [$assetB->getKey(), $assetA->getKey()], 'primary_media_asset_id' => $assetA->getKey(),
        ])->assertRedirect();
        $this->assertDatabaseHas('product_media', ['product_id' => $productA->getKey(), 'media_asset_id' => $assetB->getKey(), 'position' => 0, 'is_primary' => false]);
        $this->assertDatabaseHas('product_media', ['product_id' => $productA->getKey(), 'media_asset_id' => $assetA->getKey(), 'position' => 1, 'is_primary' => true]);
    }

    public function test_variant_images_work_and_cross_tenant_references_are_hidden(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $product = $this->createProduct($tenant);
        $variant = $product->variants()->sole();
        $asset = $this->createAsset($tenant, $user);
        $otherUser = User::factory()->create();
        $other = $this->createTenantFor($otherUser);
        $otherAsset = $this->createAsset($other, $otherUser);
        $otherProduct = $this->createProduct($other);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->post(route('admin.product-variants.media.store', $variant), ['media_asset_id' => $asset->getKey()])->assertRedirect();
        $this->assertSame(1, ProductVariantMedia::query()->count());
        $this->post(route('admin.product-variants.media.store', $variant), ['media_asset_id' => $otherAsset->getKey()])->assertNotFound();
        $this->post(route('admin.products.media.store', $otherProduct), ['media_asset_id' => $asset->getKey()])->assertNotFound();
    }

    public function test_delete_is_blocked_while_referenced_then_removes_all_storage_when_unused(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $product = $this->createProduct($tenant);
        $asset = $this->createAsset($tenant, $user);
        $keys = [$asset->storage_key, ...$asset->derivatives->pluck('storage_key')->all()];
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);
        $this->post(route('admin.products.media.store', $product), ['media_asset_id' => $asset->getKey()])->assertRedirect();
        $this->deleteJson(route('admin.media.destroy', $asset))->assertConflict()->assertJsonPath(
            'message',
            'Esta imagen está en uso. Quita todas las referencias de productos, variantes, logotipos y favicons antes de eliminarla.',
        );
        $this->assertDatabaseHas('media_assets', ['id' => $asset->getKey()]);
        foreach ($keys as $key) {
            Storage::disk('media-test')->assertExists($key);
        }

        $this->delete(route('admin.products.media.destroy', [$product, $asset]))->assertRedirect();
        $this->delete(route('admin.media.destroy', $asset))->assertRedirect();
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->getKey()]);
        foreach ($keys as $key) {
            Storage::disk('media-test')->assertMissing($key);
        }
    }
}
