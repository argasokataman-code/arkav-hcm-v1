<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class HcmHolidayController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'holiday.view');
        if ($forbidden) {
            return $forbidden;
        }

        $rows = Holiday::query()->orderByDesc('holiday_date')->get()->map(fn (Holiday $h) => [
            'id' => $h->id,
            'title' => $h->title,
            'holidayDate' => $h->holiday_date->toDateString(),
            'description' => $h->description ?? '',
            'isActive' => (bool) $h->is_active,
            'source' => $h->source ?: 'manual',
            'lastSyncedAt' => $h->last_synced_at?->toIso8601String(),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'linkage' => $this->buildLinkageSummary(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'holiday.create');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'holidayDate' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $h = Holiday::query()->create([
            'title' => $validated['title'],
            'holiday_date' => $validated['holidayDate'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'source' => 'manual',
            'last_synced_at' => null,
        ]);
        $this->syncHolidayCalendarRow($h);

        return response()->json(['success' => true, 'data' => ['id' => $h->id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'holiday.update');
        if ($forbidden) {
            return $forbidden;
        }

        $h = Holiday::query()->findOrFail($id);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'holidayDate' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $oldDate = $h->holiday_date?->toDateString();
        $oldTitle = (string) $h->title;

        $h->update([
            'title' => $validated['title'],
            'holiday_date' => $validated['holidayDate'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'source' => 'manual',
            'last_synced_at' => null,
        ]);

        if ($oldDate && $oldTitle) {
            HolidayCalendar::query()
                ->where('holiday_id', $h->id)
                ->delete();
        }
        $this->syncHolidayCalendarRow($h->fresh());

        return response()->json(['success' => true]);
    }

    public function syncIndonesia(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'holiday.sync');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);
        $year = (int) ($validated['year'] ?? now()->year);
        $providerUsed = 'libur.deno.dev';
        $list = null;

        try {
            $resp = Http::acceptJson()->timeout(20)->get('https://libur.deno.dev/api', [
                'year' => $year,
            ]);
        } catch (Throwable) {
            $resp = null;
        }
        if ($resp && $resp->ok()) {
            $json = $resp->json();
            if (is_array($json)) {
                $list = $json;
            }
        }

        if (! is_array($list)) {
            $providerUsed = 'date.nager.at';
            try {
                $fallbackResp = Http::acceptJson()->timeout(20)->get("https://date.nager.at/api/v3/PublicHolidays/{$year}/ID");
            } catch (Throwable) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'HOLIDAY_SYNC_UNREACHABLE',
                        'message' => 'Holiday provider is unreachable.',
                    ],
                ], 502);
            }
            if (! $fallbackResp->ok()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'HOLIDAY_SYNC_FAILED',
                        'message' => 'Failed to fetch holiday data from provider.',
                    ],
                ], 502);
            }

            $fallbackList = $fallbackResp->json();
            if (! is_array($fallbackList)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'HOLIDAY_SYNC_INVALID_PAYLOAD',
                        'message' => 'Provider payload is not valid.',
                    ],
                ], 502);
            }

            $list = $fallbackList;
        }

        $created = 0;
        $updated = 0;
        $skippedManual = 0;
        $invalidRows = 0;
        $cleanedStaleApi = 0;
        $now = Carbon::now();
        $seenApiKeys = [];

        foreach ($list as $row) {
            if (! is_array($row)) {
                $invalidRows++;
                continue;
            }

            $holidayDate = isset($row['date']) ? trim((string) $row['date']) : '';
            $title = trim((string) ($row['name'] ?? $row['localName'] ?? ''));
            if ($providerUsed === 'date.nager.at' && isset($row['localName']) && trim((string) $row['localName']) !== '') {
                $title = trim((string) $row['localName']);
            }
            if ($holidayDate === '' || $title === '') {
                $invalidRows++;
                continue;
            }

            try {
                $normalizedDate = Carbon::parse($holidayDate)->toDateString();
            } catch (Throwable) {
                $invalidRows++;
                continue;
            }
            $normalizedTitle = preg_replace('/\s+/', ' ', $title) ?: $title;
            $seenKey = $normalizedDate.'|'.mb_strtolower($normalizedTitle);
            $seenApiKeys[$seenKey] = true;

            $types = isset($row['types']) && is_array($row['types']) ? $row['types'] : [];
            $description = $types !== []
                ? 'Synced from '.$providerUsed.' ['.implode(', ', array_map('strval', $types)).']'
                : 'Synced from '.$providerUsed;

            $existingManual = Holiday::query()
                ->whereDate('holiday_date', $normalizedDate)
                ->where('title', $normalizedTitle)
                ->where('source', 'manual')
                ->first();
            if ($existingManual) {
                $this->syncHolidayCalendarRow($existingManual);
                $skippedManual++;
                continue;
            }

            $holiday = Holiday::query()
                ->where('source', 'api')
                ->whereDate('holiday_date', $normalizedDate)
                ->where('title', $normalizedTitle)
                ->first();

            if (! $holiday) {
                $holiday = Holiday::query()
                    ->where('source', 'api')
                    ->whereDate('holiday_date', $normalizedDate)
                    ->orderByDesc('id')
                    ->first();
            }

            if ($holiday) {
                $holiday->update([
                    'title' => $normalizedTitle,
                    'holiday_date' => $normalizedDate,
                    'description' => $description,
                    'is_active' => true,
                    'last_synced_at' => $now,
                ]);
                $this->syncHolidayCalendarRow($holiday->fresh());
                $updated++;
            } else {
                $createdHoliday = Holiday::query()->create([
                    'title' => $normalizedTitle,
                    'holiday_date' => $normalizedDate,
                    'description' => $description,
                    'is_active' => true,
                    'source' => 'api',
                    'last_synced_at' => $now,
                ]);
                $this->syncHolidayCalendarRow($createdHoliday);
                $created++;
            }
        }

        $staleApiRows = Holiday::query()
            ->where('source', 'api')
            ->whereYear('holiday_date', $year)
            ->get();
        foreach ($staleApiRows as $stale) {
            $staleKey = $stale->holiday_date->toDateString().'|'.mb_strtolower((string) $stale->title);
            if (isset($seenApiKeys[$staleKey])) {
                continue;
            }
            HolidayCalendar::query()->where('holiday_id', $stale->id)->delete();
            $stale->delete();
            $cleanedStaleApi++;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'created' => $created,
                'updated' => $updated,
                'skippedManual' => $skippedManual,
                'invalidRows' => $invalidRows,
                'cleanedStaleApi' => $cleanedStaleApi,
            ],
            'meta' => [
                'linkage' => $this->buildLinkageSummary(),
            ],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'holiday.update');
        if ($forbidden) {
            return $forbidden;
        }

        $holiday = Holiday::query()->findOrFail($id);
        $date = $holiday->holiday_date?->toDateString();
        $title = (string) $holiday->title;

        $holiday->delete();

        if ($date && $title) {
            HolidayCalendar::query()
                ->where('holiday_id', $holiday->id)
                ->delete();
        }

        return response()->json(['success' => true]);
    }

    private function buildLinkageSummary(): array
    {
        $holidayRows = Holiday::query()->count();
        $calendarRows = HolidayCalendar::query()->count();
        $linkedRows = HolidayCalendar::query()->whereNotNull('holiday_id')->count();

        return [
            'holidayRows' => $holidayRows,
            'calendarRows' => $calendarRows,
            'linkedRows' => $linkedRows,
            'unlinkedRows' => max(0, $calendarRows - $linkedRows),
            'manualRows' => Holiday::query()->where('source', 'manual')->count(),
            'apiRows' => Holiday::query()->where('source', 'api')->count(),
        ];
    }

    private function syncHolidayCalendarRow(Holiday $holiday): void
    {
        $date = $holiday->holiday_date?->toDateString();
        if (! $date) {
            return;
        }

        $name = (string) ($holiday->title ?? 'Holiday');
        $isJointLeave = str_contains(mb_strtolower($name), 'cuti bersama');

        HolidayCalendar::query()
            ->where('holiday_id', $holiday->id)
            ->delete();

        HolidayCalendar::query()->updateOrCreate(
            [
                'company_id' => null,
                'date' => $date,
                'name' => $name,
            ],
            [
                'holiday_id' => $holiday->id,
                'is_national' => true,
                'is_joint_leave' => $isJointLeave,
                'deduct_from_leave' => false,
                'source' => (string) ($holiday->source ?: 'manual'),
                'last_synced_at' => $holiday->last_synced_at,
            ]
        );
    }
}
