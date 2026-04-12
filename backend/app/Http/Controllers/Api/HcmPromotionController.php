<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\HcmPromotion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmPromotionController extends Controller
{
    use EnsuresHcmAdmin;

    private function promotionForbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'AUTH_FORBIDDEN',
                'message' => 'Forbidden.',
            ],
        ], 403);
    }

    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $v = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HcmPromotion::query()
            ->with(['user:id,name,email'])
            ->orderByDesc('promotion_date')
            ->orderByDesc('id');

        if (! empty($v['q'])) {
            $q = trim((string) $v['q']);
            $query->where(function ($b) use ($q): void {
                $b->where('department', 'like', '%'.$q.'%')
                    ->orWhere('designation_from', 'like', '%'.$q.'%')
                    ->orWhere('designation_to', 'like', '%'.$q.'%')
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'));
            });
        }
        if (! empty($v['dateFrom'])) {
            $query->whereDate('promotion_date', '>=', $v['dateFrom']);
        }
        if (! empty($v['dateTo'])) {
            $query->whereDate('promotion_date', '<=', $v['dateTo']);
        }

        $rows = $query->paginate((int) ($v['perPage'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => $rows->getCollection()->map(fn (HcmPromotion $p) => $this->payload($p))->values(),
            'meta' => [
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    /**
     * Single promotion (HCM admin: any; employee: own row only).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $p = HcmPromotion::query()->with(['user:id,name,email'])->find($id);
        if (! $p) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PROMOTION_NOT_FOUND',
                    'message' => 'Promotion not found.',
                ],
            ], 404);
        }

        $auth = $request->user();
        if (! $auth->isHcmAdmin() && (int) $auth->id !== (int) $p->user_id) {
            return $this->promotionForbidden();
        }

        return response()->json([
            'success' => true,
            'data' => $this->payload($p),
        ]);
    }

    /**
     * Promotions for one employee (HCM admin: any userId; employee: self only).
     */
    public function promotionsForUser(Request $request, int $userId): JsonResponse
    {
        $auth = $request->user();
        if (! $auth->isHcmAdmin() && (int) $auth->id !== (int) $userId) {
            return $this->promotionForbidden();
        }

        User::query()->findOrFail($userId);

        $v = $request->validate([
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $rows = HcmPromotion::query()
            ->with(['user:id,name,email'])
            ->where('user_id', $userId)
            ->orderByDesc('promotion_date')
            ->orderByDesc('id')
            ->paginate((int) ($v['perPage'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => $rows->getCollection()->map(fn (HcmPromotion $p) => $this->payload($p))->values(),
            'meta' => [
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $v = $request->validate([
            'userId' => ['required', 'integer', 'exists:users,id'],
            'department' => ['nullable', 'string', 'max:150'],
            'designationFrom' => ['nullable', 'string', 'max:150'],
            'designationTo' => ['nullable', 'string', 'max:150'],
            'promotionDate' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        User::query()->findOrFail((int) $v['userId']);

        $p = HcmPromotion::query()->create([
            'user_id' => (int) $v['userId'],
            'department' => isset($v['department']) ? trim((string) $v['department']) : null,
            'designation_from' => isset($v['designationFrom']) ? trim((string) $v['designationFrom']) : null,
            'designation_to' => isset($v['designationTo']) ? trim((string) $v['designationTo']) : null,
            'promotion_date' => $v['promotionDate'],
            'notes' => isset($v['notes']) ? trim((string) $v['notes']) : null,
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $p->id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $p = HcmPromotion::query()->findOrFail($id);

        $v = $request->validate([
            'userId' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'department' => ['sometimes', 'nullable', 'string', 'max:150'],
            'designationFrom' => ['sometimes', 'nullable', 'string', 'max:150'],
            'designationTo' => ['sometimes', 'nullable', 'string', 'max:150'],
            'promotionDate' => ['sometimes', 'required', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $payload = [];
        if (array_key_exists('userId', $v)) {
            $payload['user_id'] = (int) $v['userId'];
        }
        if (array_key_exists('department', $v)) {
            $payload['department'] = $v['department'] !== null ? trim((string) $v['department']) : null;
        }
        if (array_key_exists('designationFrom', $v)) {
            $payload['designation_from'] = $v['designationFrom'] !== null ? trim((string) $v['designationFrom']) : null;
        }
        if (array_key_exists('designationTo', $v)) {
            $payload['designation_to'] = $v['designationTo'] !== null ? trim((string) $v['designationTo']) : null;
        }
        if (array_key_exists('promotionDate', $v)) {
            $payload['promotion_date'] = $v['promotionDate'];
        }
        if (array_key_exists('notes', $v)) {
            $payload['notes'] = $v['notes'] !== null ? trim((string) $v['notes']) : null;
        }

        if ($payload !== []) {
            $p->update($payload);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        HcmPromotion::query()->whereKey($id)->delete();
        return response()->json(['success' => true]);
    }

    private function payload(HcmPromotion $p): array
    {
        return [
            'id' => $p->id,
            'employee' => $p->user ? ['id' => $p->user->id, 'name' => $p->user->name, 'email' => $p->user->email] : null,
            'department' => $p->department ?? '',
            'designationFrom' => $p->designation_from ?? '',
            'designationTo' => $p->designation_to ?? '',
            'promotionDate' => $p->promotion_date?->toDateString(),
            'notes' => $p->notes ?? '',
            'createdAt' => $p->created_at?->toIso8601String(),
        ];
    }
}

