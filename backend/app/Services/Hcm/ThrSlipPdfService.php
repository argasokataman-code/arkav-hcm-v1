<?php

namespace App\Services\Hcm;

use App\Models\HcmThrBatch;
use App\Models\HcmThrBatchLine;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

final class ThrSlipPdfService
{
    public function generateAndStore(HcmThrBatch $batch, HcmThrBatchLine $line): ?string
    {
        $fresh = $line->fresh();
        if ($fresh !== null) {
            $line = $fresh;
        }

        $relative = 'thr-slips/'.$batch->calendar_year.'/'.$line->user_id.'-batch-'.$batch->id.'.pdf';
        $full = storage_path('app/private/'.$relative);
        File::ensureDirectoryExists(dirname($full));

        $logoDataUri = $this->logoAsDataUri();

        $html = View::make('pdf.thr-slip', [
            'line' => $line,
            'batch' => $batch,
            'logoDataUri' => $logoDataUri,
            'companyAddress' => config('hcm.organization_address'),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        File::put($full, $dompdf->output());

        return $relative;
    }

    private function logoAsDataUri(): ?string
    {
        $candidates = [
            public_path('build/img/image111.png'),
            public_path('build/img/favicon.png'),
        ];
        foreach ($candidates as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }
            $raw = @file_get_contents($path);
            if (! is_string($raw) || $raw === '') {
                continue;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                default => 'application/octet-stream',
            };

            return 'data:'.$mime.';base64,'.base64_encode($raw);
        }

        return null;
    }
}
