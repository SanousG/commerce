<?php

return [
    'disk' => env('MEDIA_DISK', 'local'),
    'max_image_mb' => (int) env('MEDIA_MAX_IMAGE_MB', 10),
    'max_width' => (int) env('MEDIA_MAX_WIDTH', 6000),
    'max_height' => (int) env('MEDIA_MAX_HEIGHT', 6000),
    'max_pixels' => (int) env('MEDIA_MAX_PIXELS', 20_000_000),
    'jpeg_quality' => (int) env('MEDIA_JPEG_QUALITY', 90),
    'webp_quality' => (int) env('MEDIA_WEBP_QUALITY', 85),
    'derivatives' => [
        'THUMBNAIL' => 300,
        'MEDIUM' => 1200,
    ],
    'page_size' => 24,
];
