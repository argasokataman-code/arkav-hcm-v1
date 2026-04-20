<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\HcmOvertimeType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HcmOvertimeTypeController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request): JsonResponse
    {
        $query = HcmOvertimeType::query()->orderBy('sort_order')->orderBy('name');
        $canManage = $this->hasPermission($request, 'attendance.manage');
        if (! $canManage) {
            $query->where('is_active', true);
        }

        $rows = $query->get()->map(fn (HcmOvertimeType $t) => $this->serializeType($t))->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'attendance.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'paymentMultiplier' => ['nullable', 'numeric', 'min:0.01', 'max:99.99'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if (! empty($validated['code']) && HcmOvertimeType::query()->where('code', $validated['code'])->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'code already exists.',
                ],
            ], 422);
        }

        $code = $this->uniqueCode($validated['code'] ?? null, $validated['name']);

        $t = HcmOvertimeType::query()->create([
            'code' => $code,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'payment_multiplier' => $validated['paymentMultiplier'] ?? 1,
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $t->id]], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'attendance.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $query = HcmOvertimeType::query();
        $this->applyIdentifierScope($query, $id);
        $t = $query->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'paymentMultiplier' => ['required', 'numeric', 'min:0.01', 'max:99.99'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $code = $validated['code'] ?? $t->code;
        if ($code !== $t->code && HcmOvertimeType::query()->where('code', $code)->whereKeyNot($t->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'code already exists.',
                ],
            ], 422);
        }

        $t->update([
            'code' => $code,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'payment_multiplier' => $validated['paymentMultiplier'],
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? $t->sort_order),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'attendance.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $query = HcmOvertimeType::query();
        $this->applyIdentifierScope($query, $id);
        $query->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeType(HcmOvertimeType $t): array
    {
        return [
            'id' => $t->id,
            'code' => $t->code,
            'name' => $t->name,
            'description' => $t->description ?? '',
            'paymentMultiplier' => (string) $t->payment_multiplier,
            'isActive' => (bool) $t->is_active,
            'sortOrder' => (int) $t->sort_order,
        ];
    }

    private function uniqueCode(?string $requested, string $name): string
    {
        $base = $requested ?: Str::slug($name, '_');
        if ($base === '') {
            $base = 'ot_type';
        }
        $base = Str::limit($base, 60, '');
        $code = $base;
        $i = 0;
        while (HcmOvertimeType::query()->where('code', $code)->exists()) {
            $i++;
            $suffix = '_'.$i;
            $code = Str::limit($base, 64 - strlen($suffix), '').$suffix;
        }

        return $code;
    }

    private function applyIdentifierScope($query, string $identifier)
    {
        $hasUuidColumn = Schema::hasColumn((new HcmOvertimeType)->getTable(), 'uuid');
        if ($hasUuidColumn && Str::isUuid($identifier)) {
            return $query->where('uuid', $identifier);
        }

        if (ctype_digit($identifier)) {
            return $query->whereKey((int) $identifier);
        }

        return $query->whereRaw('1 = 0');
    }
}
