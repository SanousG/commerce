<?php

namespace App\Modules\Media\Application;

final readonly class ProcessedImage
{
    /** @param array<string, array{bytes: string, width: int, height: int}> $derivatives */
    public function __construct(
        public string $masterBytes,
        public string $mimeType,
        public string $extension,
        public int $width,
        public int $height,
        public array $derivatives,
    ) {}
}
