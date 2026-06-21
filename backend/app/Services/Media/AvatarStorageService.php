<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Ready for profile / employee avatars: raster only, JPEG output, small dimensions.
 */
final class AvatarStorageService
{
    public function __construct(
        private readonly ImageProcessor $imageProcessor,
        private readonly MediaFileDeleter $mediaFileDeleter,
    ) {}

    public function store(UploadedFile $file, int $userId): StoredFileResult
    {
        $disk = (string) config('media.default_disk', 'public');
        $maxKb = (int) config('media.avatar.max_kb', 2048);

        FileSizeLimiter::assertMaxKilobytes($file, $maxKb);
        MimeAndExtensionValidator::assertRasterImage($file);

        [$binary, $width, $height] = $this->imageProcessor->compressToJpeg(
            $file,
            ImageCompressionConfig::avatar(),
        );

        $directory = StoragePathResolver::avatar($userId);
        $filename = UniqueFilenameGenerator::randomWithExtension('jpg');
        $path = $directory.'/'.$filename;

        Storage::disk($disk)->put($path, $binary);
        $size = (int) Storage::disk($disk)->size($path);

        return new StoredFileResult(
            path: $path,
            disk: $disk,
            mimeType: 'image/jpeg',
            sizeBytes: $size,
            width: $width,
            height: $height,
        );
    }

    public function replace(?string $previousPath, UploadedFile $file, int $userId): StoredFileResult
    {
        $result = $this->store($file, $userId);
        $this->mediaFileDeleter->delete($previousPath);

        return $result;
    }
}
