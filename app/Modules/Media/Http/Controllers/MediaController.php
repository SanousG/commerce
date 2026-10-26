<?php

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\Application\DeleteMediaAsset;
use App\Modules\Media\Application\UploadMediaAsset;
use App\Modules\Media\Domain\Enums\MediaStatus;
use App\Modules\Media\Domain\Enums\MediaType;
use App\Modules\Media\Domain\Models\MediaAsset;
use App\Modules\Media\Http\Requests\UpdateMediaRequest;
use App\Modules\Media\Http\Requests\UploadMediaRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function index(Request $request, TenantContext $tenants): Response|JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $type = (string) $request->query('type', MediaType::Image->value);
        abort_unless(in_array($type, array_column(MediaType::cases(), 'value'), true), 422);

        $assets = $tenants->get()->mediaAssets()->where('status', MediaStatus::Ready)
            ->where('type', $type)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'ilike', "%{$search}%")
                ->orWhere('alt_text', 'ilike', "%{$search}%")
                ->orWhere('original_filename', 'ilike', "%{$search}%")))
            ->withCount(['productMedia as product_usage_count', 'productVariantMedia as variant_usage_count'])
            ->latest()->paginate((int) config('media.page_size'))->withQueryString()
            ->through(fn (MediaAsset $asset): array => $this->serialize($asset));

        if ($request->expectsJson()) {
            return response()->json($assets);
        }

        return Inertia::render('admin/media/Index', [
            'media' => $assets,
            'filters' => ['search' => $search, 'type' => $type],
            'limits' => ['max_image_mb' => config('media.max_image_mb')],
        ]);
    }

    public function store(UploadMediaRequest $request, TenantContext $tenants, UploadMediaAsset $upload): RedirectResponse|JsonResponse
    {
        $asset = $upload->handle(
            $tenants->get(), $request->user(), $request->file('file'),
            $request->validated('title'), $request->validated('alt_text'),
        );

        return $request->expectsJson()
            ? response()->json($this->serialize($asset), 201)
            : back()->with('status', __('Image uploaded.'));
    }

    public function show(TenantContext $tenants, string $mediaAsset): JsonResponse
    {
        $asset = $this->asset($tenants, $mediaAsset)
            ->loadCount(['productMedia as product_usage_count', 'productVariantMedia as variant_usage_count']);

        return response()->json($this->serialize($asset));
    }

    public function update(UpdateMediaRequest $request, TenantContext $tenants, string $mediaAsset): RedirectResponse|JsonResponse
    {
        $asset = $this->asset($tenants, $mediaAsset);
        $asset->update([
            'title' => filled($request->validated('title')) ? trim((string) $request->validated('title')) : null,
            'alt_text' => filled($request->validated('alt_text')) ? trim((string) $request->validated('alt_text')) : null,
        ]);

        return $request->expectsJson()
            ? response()->json($this->serialize($asset->refresh()))
            : back()->with('status', __('Image details updated.'));
    }

    public function destroy(TenantContext $tenants, DeleteMediaAsset $delete, string $mediaAsset): RedirectResponse|JsonResponse
    {
        $delete->handle($this->asset($tenants, $mediaAsset));

        return request()->expectsJson() ? response()->json(status: 204) : back()->with('status', __('Image deleted.'));
    }

    public function thumbnail(TenantContext $tenants, string $mediaAsset): StreamedResponse
    {
        $asset = $this->asset($tenants, $mediaAsset);
        $derivative = $asset->derivatives()->where('variant', 'THUMBNAIL')->firstOrFail();

        return $this->stream($derivative->disk, $derivative->storage_key, $derivative->mime_type, $derivative->size_bytes);
    }

    public function file(TenantContext $tenants, string $mediaAsset): StreamedResponse
    {
        $asset = $this->asset($tenants, $mediaAsset);

        return $this->stream($asset->disk, $asset->storage_key, $asset->mime_type, $asset->size_bytes);
    }

    private function asset(TenantContext $tenants, string $id): MediaAsset
    {
        return $tenants->get()->mediaAssets()->where('status', MediaStatus::Ready)->findOrFail($id);
    }

    private function stream(string $disk, string $key, string $mime, int $size): StreamedResponse
    {
        $stream = Storage::disk($disk)->readStream($key);
        if (! is_resource($stream)) {
            throw new RuntimeException('The media file is unavailable.');
        }

        return response()->stream(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) $size,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function serialize(MediaAsset $asset): array
    {
        return [
            'id' => $asset->getKey(), 'type' => $asset->type->value,
            'title' => $asset->title, 'alt_text' => $asset->alt_text,
            'original_filename' => $asset->original_filename, 'mime_type' => $asset->mime_type,
            'extension' => $asset->extension, 'size_bytes' => $asset->size_bytes,
            'width' => $asset->width, 'height' => $asset->height, 'checksum' => $asset->checksum,
            'created_at' => $asset->created_at->toISOString(),
            'usage_count' => (int) ($asset->product_usage_count ?? 0) + (int) ($asset->variant_usage_count ?? 0),
            'thumbnail_url' => route('admin.media.thumbnail', $asset),
            'file_url' => route('admin.media.file', $asset),
        ];
    }
}
