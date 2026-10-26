<?php

namespace App\Modules\Media\Application;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ImageProcessor
{
    private const FORMATS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function process(UploadedFile $file): ProcessedImage
    {
        $this->assertRuntime();
        $path = $file->getRealPath();
        if ($path === false || ! is_file($path)) {
            $this->invalid(__('The uploaded image could not be read.'));
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! is_string($mime) || ! isset(self::FORMATS[$mime])) {
            $this->invalid(__('Only genuine JPEG, PNG, or WebP images are accepted.'));
        }

        $info = @getimagesize($path);
        if ($info === false || ($info['mime'] ?? null) !== $mime) {
            $this->invalid(__('The uploaded file is not a valid image.'));
        }
        [$width, $height] = $info;
        $maxWidth = (int) config('media.max_width');
        $maxHeight = (int) config('media.max_height');
        $maxPixels = (int) config('media.max_pixels');
        if ($width < 1 || $height < 1 || $width > $maxWidth || $height > $maxHeight || $width * $height > $maxPixels) {
            $this->invalid(__('Image dimensions must not exceed :width×:height or :pixels pixels.', [
                'width' => $maxWidth,
                'height' => $maxHeight,
                'pixels' => $maxPixels,
            ]));
        }

        $image = $this->decode($path, $mime);
        try {
            if ($mime === 'image/jpeg') {
                $image = $this->orientJpeg($image, $path);
            }
            $width = imagesx($image);
            $height = imagesy($image);
            $masterBytes = $this->encode($image, $mime);
            $derivatives = [];
            foreach ((array) config('media.derivatives') as $variant => $maxSide) {
                $resized = $this->resize($image, (int) $maxSide);
                try {
                    $derivatives[(string) $variant] = [
                        'bytes' => $this->encode($resized, 'image/webp'),
                        'width' => imagesx($resized),
                        'height' => imagesy($resized),
                    ];
                } finally {
                    imagedestroy($resized);
                }
            }

            return new ProcessedImage($masterBytes, $mime, self::FORMATS[$mime], $width, $height, $derivatives);
        } finally {
            imagedestroy($image);
        }
    }

    private function assertRuntime(): void
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp') || ! (imagetypes() & IMG_WEBP)) {
            throw new RuntimeException('Media image processing requires GD with WebP support.');
        }
    }

    private function decode(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
        };
        if (! $image instanceof GdImage) {
            $this->invalid(__('The image is corrupt or cannot be decoded safely.'));
        }

        return $image;
    }

    private function orientJpeg(GdImage $image, string $path): GdImage
    {
        $orientation = function_exists('exif_read_data') ? (int) (@exif_read_data($path, 'IFD0')['Orientation'] ?? 1) : 1;
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, in_array($orientation, [2, 5, 7], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        }
        $angle = match ($orientation) {
            3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0
        };
        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated instanceof GdImage) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        return $image;
    }

    private function resize(GdImage $source, int $maxSide): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxSide / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $target;
    }

    private function encode(GdImage $image, string $mime): string
    {
        ob_start();
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($image, null, (int) config('media.jpeg_quality')),
            'image/png' => imagepng($image, null, 6),
            'image/webp' => imagewebp($image, null, (int) config('media.webp_quality')),
        };
        $bytes = ob_get_clean();
        if (! $ok || ! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('The normalized image could not be encoded.');
        }

        return $bytes;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
