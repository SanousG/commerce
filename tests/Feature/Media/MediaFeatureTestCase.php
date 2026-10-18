<?php

namespace Tests\Feature\Media;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Catalog\Application\CreateProduct;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Media\Domain\Enums\MediaStatus;
use App\Modules\Media\Domain\Enums\MediaType;
use App\Modules\Media\Domain\Models\MediaAsset;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class MediaFeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['media.disk' => 'media-test']);
        Storage::fake('media-test');
    }

    protected function createTenantFor(User $user, SystemRole $role = SystemRole::Owner): Tenant
    {
        $tenant = Tenant::create(['name' => fake()->unique()->company(), 'slug' => fake()->unique()->slug(), 'status' => TenantStatus::Active]);
        TenantMember::create([
            'tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(),
            'role_id' => Role::query()->system($role)->sole()->getKey(),
            'status' => TenantMemberStatus::Active, 'joined_at' => now(),
        ]);

        return $tenant;
    }

    protected function createAsset(Tenant $tenant, User $user, array $attributes = []): MediaAsset
    {
        $id = (string) Str::uuid7();
        $key = "media/tenants/{$tenant->getKey()}/{$id}/master.jpg";
        Storage::disk('media-test')->put($key, 'normalized-image');
        $asset = MediaAsset::create(array_merge([
            'id' => $id, 'tenant_id' => $tenant->getKey(), 'type' => MediaType::Image,
            'disk' => 'media-test', 'storage_key' => $key, 'original_filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'size_bytes' => 16,
            'width' => 100, 'height' => 100, 'checksum' => hash('sha256', 'normalized-image'),
            'status' => MediaStatus::Ready, 'created_by_user_id' => $user->getKey(),
        ], $attributes));
        foreach (['THUMBNAIL', 'MEDIUM'] as $variant) {
            $derivativeKey = "media/tenants/{$tenant->getKey()}/{$id}/".strtolower($variant).'.webp';
            Storage::disk('media-test')->put($derivativeKey, $variant);
            $asset->derivatives()->create([
                'tenant_id' => $tenant->getKey(), 'variant' => $variant, 'disk' => 'media-test',
                'storage_key' => $derivativeKey, 'mime_type' => 'image/webp', 'width' => 100,
                'height' => 100, 'size_bytes' => strlen($variant), 'checksum' => hash('sha256', $variant),
            ]);
        }

        return $asset;
    }

    protected function createProduct(Tenant $tenant): Product
    {
        return app(CreateProduct::class)->handle($tenant, [
            'name' => fake()->unique()->words(2, true), 'description' => null,
            'sku' => 'SKU-'.Str::upper(Str::random(12)), 'barcode' => null, 'categories' => [],
        ]);
    }

    protected function imageUpload(string $name = 'photo.jpg', int $width = 800, int $height = 600): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    protected function webpUpload(): UploadedFile
    {
        $image = imagecreatetruecolor(80, 60);
        ob_start();
        imagewebp($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent('photo.webp', (string) $bytes);
    }
}
