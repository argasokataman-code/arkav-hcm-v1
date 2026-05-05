<?php

namespace App\Support;

use App\Models\HcmSptMasaHeader;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SptMasaExportService
{
    /**
     * CSV column order (DJP-style minimal).
     */
    private const COLUMNS = ['npwp', 'nik', 'nama', 'kategori_spt', 'bukti_potong_type', 'bruto', 'pph21'];

    /**
     * Stream a UTF-8 BOM CSV file to the browser.
     */
    public static function streamCsv(HcmSptMasaHeader $header): StreamedResponse
    {
        $filename = sprintf(
            'spt-masa_%s_%s.csv',
            str_replace('-', '', (string) ($header->company_uuid ?? $header->company_id)),
            $header->periode
        );

        $details = $header->details()->orderBy('id')->get();

        return response()->streamDownload(function () use ($details, $header): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility.
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row.
            fputcsv($handle, self::COLUMNS);

            foreach ($details as $detail) {
                fputcsv($handle, [
                    $detail->npwp ?? '',
                    $detail->nik ?? '',
                    $detail->nama ?? '',
                    $detail->kategori_spt ?? '',
                    $detail->bukti_potong_type ?? '',
                    number_format((float) ($detail->bruto ?? 0), 2, '.', ''),
                    number_format((float) ($detail->pph21 ?? 0), 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
