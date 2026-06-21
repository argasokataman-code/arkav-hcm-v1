<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class PolicyAttachmentStorageService
{
    public function __construct(
        private readonly ImageProcessor $imageProcessor,
        private readonly MediaFileDeleter $mediaFileDeleter,
    ) {}

    public function store(UploadedFile $file, int $policyId): StoredFileResult
    {
        $disk = (string) config('media.default_disk', 'public');
        $maxKb = (int) config('media.policy_attachment.max_kb', 12_288);

        FileSizeLimiter::assertMaxKilobytes($file, $maxKb);
        MimeAndExtensionValidator::assertPolicyAttachment($file);

        $directory = StoragePathResolver::policyAttachments($policyId);
        $mime = mime_content_type($file->getRealPath() ?: '') ?: $file->getMimeType();

        if ($mime === 'application/pdf') {
            PdfUploadHandler::assertValidPdf($file);
            $filename = UniqueFilenameGenerator::preserveSafeOriginalName($file);
            $path = $file->storeAs($directory, $filename, $disk);
            $size = (int) Storage::disk($disk)->size($path);

            return new StoredFileResult(
                path: $path,
                disk: $disk,
                mimeType: 'application/pdf',
                sizeBytes: $size,
                width: null,
                height: null,
            );
        }

        if (str_starts_with((string) $mime, 'image/')) {
            [$binary, $width, $height] = $this->imageProcessor->compressToJpeg(
                $file,
                ImageCompressionConfig::policyAttachment(),
            );

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

        throw new InvalidMediaException('Unsupported attachment type.');
    }

    public function replace(?string $previousPath, UploadedFile $file, int $policyId): StoredFileResult
    {
        $result = $this->store($file, $policyId);
        $this->mediaFileDeleter->delete($previousPath);

        return $result;
    }
}
