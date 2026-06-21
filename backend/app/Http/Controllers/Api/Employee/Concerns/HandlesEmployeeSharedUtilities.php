<?php

namespace App\Http\Controllers\Api\Employee\Concerns;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

trait HandlesEmployeeSharedUtilities
{
    private function slugCode(string $value): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', trim($value)) ?? '');

        return trim($code, '_');
    }

    private function mergePolicyMultipartFields(Request $request): void
    {
        $merged = [];

        foreach (['name', 'description', 'effectiveDate'] as $key) {
            if ($request->has($key)) {
                $value = trim((string) $request->input($key));
                $merged[$key] = $value === '' ? null : $value;
            }
        }

        if ($request->has('departmentId')) {
            $departmentId = trim((string) $request->input('departmentId'));
            $merged['departmentId'] = $departmentId === '' ? null : (is_numeric($departmentId) ? (int) $departmentId : $departmentId);
        }

        if ($merged !== []) {
            $request->merge($merged);
        }
    }

    private function profilePhotoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        return '/storage/'.$normalized;
    }

    private function policyAttachmentUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        return '/storage/'.$normalized;
    }

    private function logExportAuditTrail(Request $request, string $exportKey, string $format, int $rowCount): void
    {
        $this->logHcmActivity(
            $request,
            'employee_export',
            '',
            'exported',
            [],
            [
                'exportKey' => $exportKey,
                'format' => $format,
                'rowCount' => $rowCount,
            ],
        );
    }

    private function normalizeExportFormat(Request $request): string
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));

        return in_array($format, ['xlsx', 'csv', 'pdf'], true) ? $format : 'xlsx';
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, scalar|null>>  $rows
     */
    private function exportTabular(string $basename, string $format, array $headers, array $rows)
    {
        $filename = $basename.'-'.now()->format('Ymd-His').'.'.$format;

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }

                fprintf($handle, '﻿');
                fputcsv($handle, $headers);
                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
                fclose($handle);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        if ($format === 'pdf') {
            $options = new Options;
            $options->set('isRemoteEnabled', true);

            $dompdf = new Dompdf($options);
            $head = implode('', array_map(static fn (string $header): string => '<th>'.e($header).'</th>', $headers));
            $body = implode('', array_map(static function (array $row): string {
                $columns = implode('', array_map(static fn ($value): string => '<td>'.e((string) ($value ?? '')).'</td>', $row));

                return '<tr>'.$columns.'</tr>';
            }, $rows));

            $html = '<html><head><style>'
                .'body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#111827;}'
                .'table{width:100%;border-collapse:collapse;}'
                .'th,td{border:1px solid #d1d5db;padding:6px 8px;text-align:left;vertical-align:top;}'
                .'th{background:#f3f4f6;font-weight:600;}'
                .'</style></head><body>'
                .'<table><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table>'
                .'</body></html>';

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        return response()->streamDownload(function () use ($headers, $rows): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($headers, null, 'A1');
            if ($rows !== []) {
                $sheet->fromArray($rows, null, 'A2');
            }

            $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
            for ($index = 1; $index <= count($headers); $index++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
