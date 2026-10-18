<?php

namespace App\Modules\Media\Application;

use App\Models\User;
use App\Modules\Media\Domain\Enums\MediaDerivativeVariant;
use App\Modules\Media\Domain\Enums\MediaStatus;
use App\Modules\Media\Domain\Enums\MediaType;
use App\Modules\Media\Domain\Models\MediaAsset;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UploadMediaAsset
{
    public function __construct(private readonly ImageProcessor $images) {}

    public function handle(Tenant $tenant, User $actor, UploadedFile $file, ?string $title, ?string $altText): MediaAsset
    {
        $processed = $this->images->process($file);
        $id = (string) Str::uuid7();
        $disk = (string) config('media.disk');
        $directory = "media/tenants/{$tenant->getKey()}/{$id}";
        $masterKey = "{$directory}/master.{$processed->extension}";
        $written = [];

        try {
            $this->put($disk, $masterKey, $processed->masterBytes);
            $written[] = $masterKey;
            $derivatives = [];
            foreach ($processed->derivatives as $variant => $data) {
                $key = "{$directory}/".strtolower($variant).'.webp';
                $this->put($disk, $key, $data['bytes']);
                $written[] = $key;
                $derivatives[] = ['id' => (string) Str::uuid7(), 'tenant_id' => $tenant->getKey(),
                    'variant' => MediaDerivativeVariant::from($variant), 'disk' => $disk, 'storage_key' => $key,
                    'mime_type' => 'image/webp', 'width' => $data['width'], 'height' => $data['height'],
                    'size_bytes' => strlen($data['bytes']), 'checksum' => hash('sha256', $data['bytes'])];
            }

            return DB::transaction(function () use ($id, $tenant, $actor, $file, $title, $altText, $processed, $disk, $masterKey, $derivatives): MediaAsset {
                $asset = MediaAsset::create([
                    'id' => $id, 'tenant_id' => $tenant->getKey(), 'type' => MediaType::Image,
                    'disk' => $disk, 'storage_key' => $masterKey,
                    'original_filename' => $this->safeFilename($file->getClientOriginalName()),
                    'mime_type' => $processed->mimeType, 'extension' => $processed->extension,
                    'size_bytes' => strlen($processed->masterBytes), 'width' => $processed->width, 'height' => $processed->height,
                    'title' => filled($title) ? trim((string) $title) : null,
                    'alt_text' => filled($altText) ? trim((string) $altText) : null,
                    'checksum' => hash('sha256', $processed->masterBytes), 'status' => MediaStatus::Ready,
                    'created_by_user_id' => $actor->getKey(),
                ]);
                $asset->derivatives()->createMany($derivatives);

                return $asset->load('derivatives');
            });
        } catch (Throwable $exception) {
            foreach ($written as $key) {
                Storage::disk($disk)->delete($key);
            }
            throw $exception;
        }
    }

    private function put(string $disk, string $key, string $bytes): void
    {
        if (! Storage::disk($disk)->put($key, $bytes, ['visibility' => 'private'])) {
            throw new RuntimeException('The image could not be stored.');
        }
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $filename));

        return Str::limit($filename !== '' ? $filename : 'image', 255, '');
    }
}
