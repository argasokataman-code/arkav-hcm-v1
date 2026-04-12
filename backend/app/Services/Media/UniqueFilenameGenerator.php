<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class UniqueFilenameGenerator
{
    public static function randomWithExtension(string $extension): string
    {
        $ext = ltrim(strtolower($extension), '.');

        return Str::uuid()->toString().'.'.$ext;
    }

    /**
     * Keeps a slug of the original name plus random suffix (good for PDFs).
     */
    public static function preserveSafeOriginalName(UploadedFile $file): string
    {
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $base = Str::slug(Str::limit((string) $base, 80, '')) ?: 'file';
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');

        return $base.'-'.Str::lower(Str::random(8)).'.'.$ext;
    }
}
