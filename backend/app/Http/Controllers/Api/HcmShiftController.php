<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\HcmShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HcmShiftController extends Controller
{
    use EnsuresHcmAdmin;

    public function index(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $rows = HcmShift::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (HcmShift $s) => $this->serializeShift($s))
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:500'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if ($validated['endTime'] <= $validated['startTime']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'endTime must be after startTime.',
                ],
            ], 422);
        }

        if (! empty($validated['code']) && HcmShift::query()->where('code', $validated['code'])->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'code already exists.',
                ],
            ], 422);
        }

        $code = $this->uniqueCode($validated['code'] ?? null, $validated['name']);

        $shift = HcmShift::query()->create([
            'code' => $code,
            'name' => $validated['name'],
            'start_time' => $validated['startTime'],
            'end_time' => $validated['endTime'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $shift->id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $shift = HcmShift::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:500'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if ($validated['endTime'] <= $validated['startTime']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'endTime must be after startTime.',
                ],
            ], 422);
        }

        $code = $validated['code'] ?? $shift->code;
        if ($code !== $shift->code && HcmShift::query()->where('code', $code)->whereKeyNot($shift->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'code already exists.',
                ],
            ], 422);
        }

        $shift->update([
            'code' => $code,
            'name' => $validated['name'],
            'start_time' => $validated['startTime'],
            'end_time' => $validated['endTime'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? $shift->sort_order),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        HcmShift::query()->whereKey($id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeShift(HcmShift $s): array
    {
        $start = $this->formatTimeString($s->start_time);
        $end = $this->formatTimeString($s->end_time);

        return [
            'id' => $s->id,
            'code' => $s->code,
            'name' => $s->name,
            'startTime' => $start,
            'endTime' => $end,
            'description' => $s->description ?? '',
            'isActive' => (bool) $s->is_active,
            'sortOrder' => (int) $s->sort_order,
            'slotLabel' => $start.' - '.$end,
        ];
    }

    private function formatTimeString(mixed $v): string
    {
        if ($v === null) {
            return '00:00';
        }
        if (is_string($v) && preg_match('/^(\d{2}):(\d{2})/', $v, $m)) {
            return $m[1].':'.$m[2];
        }

        return \Carbon\Carbon::parse((string) $v)->format('H:i');
    }

    private function uniqueCode(?string $requested, string $name): string
    {
        $base = $requested ?: Str::slug($name, '_');
        if ($base === '') {
            $base = 'shift';
        }
        $base = Str::limit($base, 60, '');
        $code = $base;
        $i = 0;
        while (HcmShift::query()->where('code', $code)->exists()) {
            $i++;
            $suffix = '_'.$i;
            $code = Str::limit($base, 64 - strlen($suffix), '').$suffix;
        }

        return $code;
    }
}
