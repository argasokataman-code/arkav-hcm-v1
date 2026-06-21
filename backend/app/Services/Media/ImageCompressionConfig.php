<?php

namespace App\Services\Media;

final readonly class ImageCompressionConfig
{
    public function __construct(
        public int $maxWidth,
        public int $maxHeight,
        public int $jpegQuality,
    ) {}

    public static function policyAttachment(): self
    {
        return new self(
            maxWidth: (int) config('media.policy_attachment.image_max_width', 1920),
            maxHeight: (int) config('media.policy_attachment.image_max_height', 1920),
            jpegQuality: max(1, min(100, (int) config('media.policy_attachment.jpeg_quality', 82))),
        );
    }

    public static function avatar(): self
    {
        return new self(
            maxWidth: (int) config('media.avatar.image_max_width', 512),
            maxHeight: (int) config('media.avatar.image_max_height', 512),
            jpegQuality: max(1, min(100, (int) config('media.avatar.jpeg_quality', 85))),
        );
    }
}
