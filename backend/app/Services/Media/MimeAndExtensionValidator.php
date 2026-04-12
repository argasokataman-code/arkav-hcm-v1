<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaException;
use Illuminate\Http\UploadedFile;

final class MimeAndExtensionValidator
{
    /** @var array<string, true> */
    private const POLICY_MIMES = [
        'application/pdf' => true,
        'image/jpeg' => true,
        'image/png' => true,
        'image/gif' => true,
        'image/webp' => true,
    ];

    /** @var array<string, true> */
    private const IMAGE_MIMES = [
        'image/jpeg' => true,
        'image/png' => true,
        'image/gif' => true,
        'image/webp' => true,
    ];

    public static function assertPolicyAttachment(UploadedFile $file): void
    {
        self::assertDetectedMime($file, self::POLICY_MIMES, 'Unsupported file type for this upload.');
    }

    public static function assertRasterImage(UploadedFile $file): void
    {
        self::assertDetectedMime($file, self::IMAGE_MIMES, 'File is not a supported image type.');
    }

    /**
     * @param  array<string, true>  $allowed
     */
    private static function assertDetectedMime(UploadedFile $file, array $allowed, string $message): void
    {
        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            throw new InvalidMediaException('Upload could not be read.');
        }

        $detected = @mime_content_type($path) ?: $file->getMimeType();
        if (! $detected || ! isset($allowed[$detected])) {
            throw new InvalidMediaException($message);
        }
    }
}
