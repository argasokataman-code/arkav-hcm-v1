<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogViewerController extends Controller
{
    private const LOG_PATTERN = '/^\[(\d{4}-\d{2}-\d{2}[\d: ]+)\] (\S+)\.(\S+): (.+)$/m';

    public function index(): JsonResponse
    {
        $path = storage_path('logs');
        $files = collect();

        foreach (glob($path.'/*.log') as $file) {
            $files->push([
                'name' => basename($file),
                'size' => filesize($file),
                'sizeLabel' => $this->formatBytes(filesize($file)),
                'modifiedAt' => date('Y-m-d H:i:s', filemtime($file)),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $files->sortByDesc('modifiedAt')->values(),
        ]);
    }

    public function show(Request $request, string $filename): JsonResponse
    {
        $path = storage_path('logs/'.$filename);

        if (! file_exists($path) || ! str_ends_with($filename, '.log')) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FILE_NOT_FOUND', 'message' => 'Log file not found.'],
            ], 404);
        }

        $maxTail = 5 * 1024 * 1024;
        $size = filesize($path);
        $handle = fopen($path, 'r');

        if ($size > $maxTail) {
            fseek($handle, -$maxTail, SEEK_END);
            fgets($handle);
        }

        $raw = '';
        while (! feof($handle)) {
            $raw .= fgets($handle);
        }
        fclose($handle);

        preg_match_all(self::LOG_PATTERN, $raw, $matches, PREG_SET_ORDER);

        $entries = collect($matches)->map(fn ($m) => [
            'timestamp' => trim($m[1]),
            'env' => $m[2],
            'level' => $m[3],
            'message' => trim($m[4]),
        ])->reverse()->values();

        $perPage = min((int) ($request->get('perPage', 100)), 500);
        $page = max((int) ($request->get('page', 1)), 1);
        $total = $entries->count();

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $filename,
                'size' => $size,
                'sizeLabel' => $this->formatBytes($size),
                'entries' => $entries->forPage($page, $perPage)->values(),
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'tail' => $size > $maxTail,
            ],
        ]);
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / pow(1024, $pow), $precision).' '.$units[$pow];
    }
}
