<?php

namespace Tests\Feature\Media;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Media\Domain\Models\MediaAsset;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

class MediaUploadAndLibraryTest extends MediaFeatureTestCase
{
    public function test_owner_uploads_normalized_jpeg_with_derivatives_and_server_owned_metadata(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.media.store'), [
                'file' => $this->imageUpload(), 'title' => 'Front view', 'alt_text' => 'Black shoe',
                'tenant_id' => 'attack', 'disk' => 'public', 'storage_key' => '../../attack', 'checksum' => 'fake',
            ])->assertRedirect();

        $asset = MediaAsset::query()->sole();
        $this->assertSame($tenant->getKey(), $asset->tenant_id);
        $this->assertSame('media-test', $asset->disk);
        $this->assertSame('image/jpeg', $asset->mime_type);
        $this->assertSame(64, strlen($asset->checksum));
        $this->assertSame($user->getKey(), $asset->created_by_user_id);
        $this->assertCount(2, $asset->derivatives);
        Storage::disk('media-test')->assertExists($asset->storage_key);
        foreach ($asset->derivatives as $derivative) Storage::disk('media-test')->assertExists($derivative->storage_key);
    }

    public function test_png_and_webp_are_accepted(): void
    {
        $user = User::factory()->create(); $tenant = $this->createTenantFor($user);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);
        $this->post(route('admin.media.store'), ['file' => $this->imageUpload('photo.png')])->assertRedirect();
        $this->post(route('admin.media.store'), ['file' => $this->webpUpload()])->assertRedirect();
        $this->assertEqualsCanonicalizing(['image/png', 'image/webp'], MediaAsset::query()->pluck('mime_type')->all());
    }

    public function test_svg_fake_corrupt_oversized_and_excessive_dimensions_are_rejected(): void
    {
        $user = User::factory()->create(); $tenant = $this->createTenantFor($user);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $this->post(route('admin.media.store'), ['file' => UploadedFile::fake()->createWithContent('x.svg', $svg)])->assertInvalid('file');
        $this->post(route('admin.media.store'), ['file' => UploadedFile::fake()->createWithContent('x.jpg', 'plain text')])->assertInvalid('file');
        $this->post(route('admin.media.store'), ['file' => UploadedFile::fake()->create('huge.jpg', 10 * 1024 + 1, 'image/jpeg')])->assertInvalid('file');
        config(['media.max_width' => 50]);
        $this->post(route('admin.media.store'), ['file' => $this->imageUpload('wide.jpg', 51, 10)])->assertInvalid('file');
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_library_is_tenant_scoped_searchable_and_paginated(): void
    {
        $user = User::factory()->create(); $tenant = $this->createTenantFor($user);
        $otherUser = User::factory()->create(); $other = $this->createTenantFor($otherUser);
        foreach (range(1, 25) as $number) $this->createAsset($tenant, $user, ['title' => "Visible {$number}"]);
        $this->createAsset($other, $otherUser, ['title' => 'Secret image']);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.media.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('admin/media/Index')->has('media.data', 24)->where('media.total', 25));
        $this->get(route('admin.media.index', ['search' => 'Secret']))
            ->assertInertia(fn (Assert $page) => $page->has('media.data', 0));
        $this->get(route('admin.media.index', ['search' => 'Visible 25']))
            ->assertInertia(fn (Assert $page) => $page->has('media.data', 1));
    }

    public function test_cross_tenant_read_update_delete_and_private_file_are_not_found(): void
    {
        $user = User::factory()->create(); $tenant = $this->createTenantFor($user);
        $otherUser = User::factory()->create(); $other = $this->createTenantFor($otherUser);
        $asset = $this->createAsset($other, $otherUser);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);
        $this->get(route('admin.media.show', $asset))->assertNotFound();
        $this->patch(route('admin.media.update', $asset), ['title' => 'Attack'])->assertNotFound();
        $this->delete(route('admin.media.destroy', $asset))->assertNotFound();
        $this->get(route('admin.media.thumbnail', $asset))->assertNotFound();
    }

    public function test_thumbnail_has_safe_headers_and_requires_authentication(): void
    {
        $user = User::factory()->create(); $tenant = $this->createTenantFor($user); $asset = $this->createAsset($tenant, $user);
        $this->get(route('admin.media.thumbnail', $asset))->assertRedirect(route('login'));
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.media.thumbnail', $asset))->assertOk()
            ->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_member_can_view_but_cannot_upload_manage_or_delete(): void
    {
        $user = User::factory()->create(); $tenant = $this->createTenantFor($user, SystemRole::Member);
        $asset = $this->createAsset($tenant, $user);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);
        $this->get(route('admin.media.index'))->assertOk();
        $this->post(route('admin.media.store'), ['file' => $this->imageUpload()])->assertForbidden();
        $this->patch(route('admin.media.update', $asset), ['title' => 'No'])->assertForbidden();
        $this->delete(route('admin.media.destroy', $asset))->assertForbidden();
    }
}
