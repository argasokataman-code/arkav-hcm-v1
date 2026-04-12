<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaException;
use Illuminate\Http\UploadedFile;

final class PdfUploadHandler
{
    public static function assertValidPdf(UploadedFile $file): void
    {
        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            throw new InvalidMediaException('PDF could not be read.');
        }

        $detected = @mime_content_type($path) ?: '';
        if ($detected !== 'application/pdf') {
            throw new InvalidMediaException('Expected a PDF file.');
        }

        $header = @file_get_contents($path, false, null, 0, 5) ?: '';
        if (! str_starts_with($header, '%PDF')) {
            throw new InvalidMediaException('Invalid PDF file header.');
        }
    }
}
