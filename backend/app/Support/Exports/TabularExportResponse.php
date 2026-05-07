<?php

namespace App\Support\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TabularExportResponse
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<scalar|null>>  $rows
     */
    public static function download(array $headers, array $rows, string $filenameBase, string $format = 'xlsx', string $sheetTitle = 'Export'): StreamedResponse
    {
        $format = strtolower(trim($format));
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            $format = 'xlsx';
        }

        $safeBase = trim($filenameBase) !== '' ? $filenameBase : 'export-'.now()->format('YmdHis');
        $filename = $safeBase.'.'.$format;

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows): void {
                $stream = fopen('php://output', 'wb');
                if (! $stream) {
                    return;
                }

                fwrite($stream, "\xEF\xBB\xBF");
                fputcsv($stream, $headers);

                foreach ($rows as $row) {
                    fputcsv($stream, $row);
                }

                fclose($stream);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        return response()->streamDownload(function () use ($headers, $rows, $sheetTitle): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(substr($sheetTitle, 0, 31));

            foreach ($headers as $column => $label) {
                $cell = Coordinate::stringFromColumnIndex($column + 1).'1';
                $sheet->setCellValue($cell, $label);
            }

            $rowIndex = 2;
            foreach ($rows as $row) {
                foreach ($row as $column => $value) {
                    $cell = Coordinate::stringFromColumnIndex($column + 1).(string) $rowIndex;
                    $sheet->setCellValue($cell, (string) ($value ?? ''));
                }
                $rowIndex++;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}