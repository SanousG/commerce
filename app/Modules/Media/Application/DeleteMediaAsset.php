<?php

namespace App\Modules\Media\Application;

use App\Modules\Media\Domain\Enums\MediaStatus;
use App\Modules\Media\Domain\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class DeleteMediaAsset
{
    public function handle(MediaAsset $asset): void
    {
        $keys = DB::transaction(function () use ($asset): array {
            $locked = MediaAsset::query()->lockForUpdate()->findOrFail($asset->getKey());
            if ($locked->productMedia()->exists() || $locked->productVariantMedia()->exists()
                || $locked->storeLogoSettings()->exists() || $locked->storeFaviconSettings()->exists()) {
                throw new ConflictHttpException('This image is in use. Remove every product, variant, logo, and favicon reference before deleting it.');
            }
            $locked->load('derivatives')->update(['status' => MediaStatus::Deleting]);

            return [$locked->disk, collect([$locked->storage_key])->merge($locked->derivatives->pluck('storage_key'))->all()];
        });

        [$disk, $storageKeys] = $keys;
        try {
            if (! Storage::disk($disk)->delete($storageKeys)) {
                throw new RuntimeException('One or more media files could not be deleted.');
            }
        } catch (Throwable $exception) {
            MediaAsset::query()->whereKey($asset->getKey())->update(['status' => MediaStatus::Ready]);
            throw $exception;
        }

        DB::transaction(fn () => MediaAsset::query()->whereKey($asset->getKey())->where('status', MediaStatus::Deleting)->delete());
    }
}
