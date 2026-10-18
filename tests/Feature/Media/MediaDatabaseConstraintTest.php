<?php

namespace Tests\Feature\Media;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\ProductMedia;
use Illuminate\Database\QueryException;

class MediaDatabaseConstraintTest extends MediaFeatureTestCase
{
    public function test_database_partial_index_rejects_two_primary_images_for_one_product(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $product = $this->createProduct($tenant);
        $assetA = $this->createAsset($tenant, $user);
        $assetB = $this->createAsset($tenant, $user);
        ProductMedia::create([
            'tenant_id' => $tenant->getKey(), 'product_id' => $product->getKey(),
            'media_asset_id' => $assetA->getKey(), 'is_primary' => true, 'position' => 0,
        ]);

        $this->expectException(QueryException::class);
        ProductMedia::create([
            'tenant_id' => $tenant->getKey(), 'product_id' => $product->getKey(),
            'media_asset_id' => $assetB->getKey(), 'is_primary' => true, 'position' => 1,
        ]);
    }

    public function test_database_composite_foreign_key_rejects_cross_tenant_media_reference(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $product = $this->createProduct($tenant);
        $otherUser = User::factory()->create();
        $other = $this->createTenantFor($otherUser);
        $asset = $this->createAsset($other, $otherUser);

        $this->expectException(QueryException::class);
        ProductMedia::create([
            'tenant_id' => $tenant->getKey(), 'product_id' => $product->getKey(),
            'media_asset_id' => $asset->getKey(), 'is_primary' => false, 'position' => 0,
        ]);
    }
}
