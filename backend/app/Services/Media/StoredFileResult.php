<?php

namespace App\Services\Media;

final readonly class StoredFileResult
{
    public function __construct(
        public string $path,
        public string $disk,
        public string $mimeType,
        public int $sizeBytes,
        public ?int $width = null,
        public ?int $height = null,
    ) {}
}
