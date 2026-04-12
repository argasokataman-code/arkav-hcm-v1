<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaException;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Throwable;

final class ImageProcessor
{
    public function __construct(
        private readonly ImageManager $manager,
    ) {
    }

    /**
     * Resize (scale down only) and encode to JPEG bytes.
     *
     * @return array{0: string, 1: int, 2: int} binary, width, height
     */
    public function compressToJpeg(UploadedFile $file, ImageCompressionConfig $config): array
    {
        MimeAndExtensionValidator::assertRasterImage($file);

        $path = $file->getRealPath();
        if (! $path) {
            throw new InvalidMediaException('Temporary upload path missing.');
        }

        try {
            $image = $this->manager->read($path);
        } catch (Throwable $e) {
            throw new InvalidMediaException('Could not decode image: '.$e->getMessage(), 0, $e);
        }

        $image->scaleDown($config->maxWidth, $config->maxHeight);

        $width = $image->width();
        $height = $image->height();

        try {
            $encoded = $image->toJpeg(quality: $config->jpegQuality);
            $binary = $encoded->toString();
        } catch (Throwable $e) {
            throw new InvalidMediaException('Could not encode image: '.$e->getMessage(), 0, $e);
        }

        return [$binary, $width, $height];
    }
}
