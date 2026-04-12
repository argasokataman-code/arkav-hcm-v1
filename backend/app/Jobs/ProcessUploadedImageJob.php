<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Stub for async image work. Dispatch when uploads should not block the HTTP request.
 * Implement handle() using ImageProcessor + ImageCompressionConfig + Storage.
 */
class ProcessUploadedImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $tempRelativePath,
        public string $finalRelativePath,
        public string $disk = 'public',
        public string $preset = 'policy_attachment',
    ) {
    }

    public function handle(): void
    {
        //
    }
}
