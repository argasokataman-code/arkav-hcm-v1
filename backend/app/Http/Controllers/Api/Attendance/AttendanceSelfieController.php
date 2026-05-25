<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceSelfieController extends BaseAttendanceController
{
    /**
     * @return array{binary: string, mime: string}|null
     */
    private function parseSelfieImagePayload(string $payload): ?array
    {
        $raw = trim($payload);
        if ($raw === '') {
            return null;
        }

        $declaredMime = null;
        $base64Data   = $raw;

        if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/s', $raw, $matches) === 1) {
            $declaredMime = strtolower((string) ($matches[1] ?? ''));
            $base64Data   = (string) ($matches[2] ?? '');
        }

        $base64Data = str_replace(["\r", "\n", ' '], '', $base64Data);
        if ($base64Data === '') {
            return null;
        }

        $binary = base64_decode($base64Data, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        if (strlen($binary) > self::SELFIE_MAX_BYTES) {
            return null;
        }

        $imageInfo    = @getimagesizefromstring($binary);
        $detectedMime = strtolower((string) ($imageInfo['mime'] ?? ''));
        if (! isset(self::SELFIE_ALLOWED_MIME_TO_EXT[$detectedMime])) {
            return null;
        }

        if ($declaredMime !== null && $declaredMime !== $detectedMime) {
            return null;
        }

        return [
            'binary' => $binary,
            'mime'   => $detectedMime,
        ];
    }

    public function meSelfie(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'selfie_base64' => 'required|string',
            'timestamp'     => 'nullable|integer',
        ]);

        try {
            $user            = $request->user();
            $activeCompanyId = $this->activeCompanyId($request);
            $workDate        = now('UTC')->setTimezone($this->tz())->toDateString();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'error'   => [
                        'code'    => 'AUTH_UNAUTHORIZED',
                        'message' => 'Missing authentication token.',
                    ],
                ], 401);
            }

            $attendanceQuery = AttendanceRecord::query();
            $this->applyTenantScope($attendanceQuery, $activeCompanyId);
            $attendance = $attendanceQuery
                ->where('user_id', $user->id)
                ->whereDate('work_date', $workDate)
                ->first();

            if (! $attendance || ! $attendance->check_in_at) {
                return response()->json([
                    'success' => false,
                    'error'   => [
                        'code'    => 'ATTENDANCE_NOT_STARTED',
                        'message' => 'Harap lakukan punch in terlebih dahulu sebelum mengambil selfie.',
                    ],
                ], 422);
            }

            $parsedImage = $this->parseSelfieImagePayload((string) $validated['selfie_base64']);
            if ($parsedImage === null) {
                return response()->json([
                    'success' => false,
                    'error'   => [
                        'code'    => 'VALIDATION_ERROR',
                        'message' => 'Data selfie tidak valid. Gunakan format JPEG/PNG/WEBP maksimal 5MB.',
                    ],
                ], 422);
            }

            ['binary' => $imageBinary, 'mime' => $detectedMime] = $parsedImage;
            $extension = self::SELFIE_ALLOWED_MIME_TO_EXT[$detectedMime] ?? 'jpg';

            $filename = sprintf(
                'selfie/%d/%s_%s.%s',
                (int) ($activeCompanyId ?? 0),
                $user->id,
                $workDate . '_' . now('UTC')->timestamp,
                $extension
            );

            Storage::disk('private')->put($filename, $imageBinary);
            $path = $filename;
            $hash = hash('sha256', $imageBinary);

            $attendance->update([
                'selfie_path'            => $path,
                'selfie_encrypted_hash'  => $hash,
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'attendance_id' => $attendance->id,
                    'selfie_path'   => $path,
                    'uploaded_at'   => $attendance->updated_at,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Selfie upload error', [
                'user_id' => $request->user()?->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INTERNAL_ERROR',
                    'message' => 'Gagal menyimpan selfie, coba lagi nanti.',
                ],
            ], 500);
        }
    }

    public function meSelfieStatus(Request $request): JsonResponse
    {
        try {
            $user            = $request->user();
            $activeCompanyId = $this->activeCompanyId($request);
            $workDate        = now('UTC')->setTimezone($this->tz())->toDateString();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'error'   => [
                        'code'    => 'AUTH_UNAUTHORIZED',
                        'message' => 'Missing authentication token.',
                    ],
                ], 401);
            }

            $attendanceQuery = AttendanceRecord::query();
            $this->applyTenantScope($attendanceQuery, $activeCompanyId);
            $attendance = $attendanceQuery
                ->where('user_id', $user->id)
                ->whereDate('work_date', $workDate)
                ->first();

            if (! $attendance) {
                return response()->json([
                    'success' => true,
                    'data'    => [
                        'has_selfie' => false,
                        'selfie'     => null,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'has_selfie' => (bool) $attendance->selfie_path,
                    'selfie'     => $attendance->selfie_path ? [
                        'path'         => $attendance->selfie_path,
                        'uploaded_at'  => $attendance->updated_at,
                        'is_encrypted' => true,
                    ] : null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INTERNAL_ERROR',
                    'message' => 'Failed to fetch selfie status.',
                ],
            ], 500);
        }
    }

    public function adminSelfieDownload(Request $request, string $attendanceId): BinaryFileResponse|JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'attendance.admin');
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $query = AttendanceRecord::query();
        $this->applyTenantScope($query, $companyId);
        $this->applyIdentifierScope($query, $attendanceId, true);
        $rec = $query->first();

        if (! $rec) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ATTENDANCE_NOT_FOUND',
                    'message' => 'Attendance record not found.',
                ],
            ], 404);
        }

        $path = ltrim((string) $rec->selfie_path, '/');
        if ($path === '') {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'SELFIE_NOT_FOUND',
                    'message' => 'Selfie not found for this attendance record.',
                ],
            ], 404);
        }

        if (! Storage::disk('private')->exists($path)) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'SELFIE_FILE_MISSING',
                    'message' => 'Selfie file missing on storage.',
                ],
            ], 404);
        }

        $fullPath     = Storage::disk('private')->path($path);
        $downloadName = basename($path);

        return response()->download($fullPath, $downloadName);
    }
}
