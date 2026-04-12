<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaException;
use Illuminate\Http\UploadedFile;

final class FileSizeLimiter
{
    public static function assertMaxKilobytes(UploadedFile $file, int $maxKb): void
    {
        if ($maxKb <= 0) {
            return;
        }

        $maxBytes = $maxKb * 1024;
        $size = $file->getSize();
        if ($size !== false && $size > $maxBytes) {
            throw new InvalidMediaException("File is larger than {$maxKb} KB.");
        }
    }
}
