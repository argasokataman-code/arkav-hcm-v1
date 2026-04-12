<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;

final class MediaFileDeleter
{
    public function delete(?string $relativePath, ?string $disk = null): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $disk = $disk ?? (string) config('media.default_disk', 'public');
        Storage::disk($disk)->delete($relativePath);
    }
}
