<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\HcmTermination;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmTerminationController extends Controller
{
    use EnsuresHcmAdmin;

    private const STATUSES = ['pending', 'approved', 'cancelled'];

    private function terminationForbidden(): JsonResponse
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

        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $v = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->with(['user:id,name,email'])
            ->orderByDesc('termination_date')
            ->orderByDesc('id');

        if (! empty($v['q'])) {
            $q = trim((string) $v['q']);
            $query->where(function ($b) use ($q): void {
                $b->where('department', 'like', '%'.$q.'%')
                    ->orWhere('termination_type', 'like', '%'.$q.'%')
                    ->orWhere('reason', 'like', '%'.$q.'%')
                    ->orWhere('status', 'like', '%'.$q.'%')
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'));
            });
        }
        if (! empty($v['dateFrom'])) {
            $query->whereDate('termination_date', '>=', $v['dateFrom']);
        }
        if (! empty($v['dateTo'])) {
            $query->whereDate('termination_date', '<=', $v['dateTo']);
        }

        $rows = $query->paginate((int) ($v['perPage'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => $rows->getCollection()->map(fn (HcmTermination $t) => $this->payload($t))->values(),
            'meta' => [
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $t = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->with(['user:id,name,email'])
            ->find($id);
        if (! $t) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TERMINATION_NOT_FOUND',
                    'message' => 'Termination not found.',
                ],
            ], 404);
        }

        $auth = $request->user();
        if (! $auth->isHcmAdmin() && (int) $auth->id !== (int) $t->user_id) {
            return $this->terminationForbidden();
        }

        return response()->json([
            'success' => true,
            'data' => $this->payload($t),
        ]);
    }

    public function terminationsForUser(Request $request, int $userId): JsonResponse
    {
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $auth = $request->user();
        if (! $auth->isHcmAdmin() && (int) $auth->id !== (int) $userId) {
            return $this->terminationForbidden();
        }

        User::query()->findOrFail($userId);

        $v = $request->validate([
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $rows = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->with(['user:id,name,email'])
            ->where('user_id', $userId)
            ->orderByDesc('termination_date')
            ->orderByDesc('id')
            ->paginate((int) ($v['perPage'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => $rows->getCollection()->map(fn (HcmTermination $t) => $this->payload($t))->values(),
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

        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $v = $request->validate([
            'userId' => ['required', 'integer', 'exists:users,id'],
            'department' => ['nullable', 'string', 'max:150'],
            'terminationType' => ['required', 'string', 'max:150'],
            'reason' => ['required', 'string', 'max:2000'],
            'noticeDate' => ['required', 'date'],
            'terminationDate' => ['required', 'date', 'after_or_equal:noticeDate'],
            'status' => ['nullable', 'string', 'in:'.implode(',', self::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        User::query()->findOrFail((int) $v['userId']);

        $t = HcmTermination::query()->create([
            'company_id' => $activeCompanyId,
            'user_id' => (int) $v['userId'],
            'department' => isset($v['department']) ? trim((string) $v['department']) : null,
            'termination_type' => trim((string) $v['terminationType']),
            'reason' => trim((string) $v['reason']),
            'notice_date' => $v['noticeDate'],
            'termination_date' => $v['terminationDate'],
            'status' => $v['status'] ?? 'pending',
            'notes' => isset($v['notes']) ? trim((string) $v['notes']) : null,
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $t->id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $t = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->findOrFail($id);

        $v = $request->validate([
            'userId' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'department' => ['sometimes', 'nullable', 'string', 'max:150'],
            'terminationType' => ['sometimes', 'required', 'string', 'max:150'],
            'reason' => ['sometimes', 'required', 'string', 'max:2000'],
            'noticeDate' => ['sometimes', 'required', 'date'],
            'terminationDate' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', 'string', 'in:'.implode(',', self::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (isset($v['noticeDate'], $v['terminationDate'])) {
            if (strtotime((string) $v['terminationDate']) < strtotime((string) $v['noticeDate'])) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The termination date must be on or after the notice date.',
                    ],
                ], 422);
            }
        } elseif (isset($v['terminationDate'])) {
            if ($t->notice_date && $t->notice_date->gt(Carbon::parse($v['terminationDate']))) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The termination date must be on or after the notice date.',
                    ],
                ], 422);
            }
        } elseif (isset($v['noticeDate'])) {
            if ($t->termination_date && Carbon::parse($v['noticeDate'])->gt($t->termination_date)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The termination date must be on or after the notice date.',
                    ],
                ], 422);
            }
        }

        $payload = [];
        if (array_key_exists('userId', $v)) {
            $payload['user_id'] = (int) $v['userId'];
        }
        if (array_key_exists('department', $v)) {
            $payload['department'] = $v['department'] !== null ? trim((string) $v['department']) : null;
        }
        if (array_key_exists('terminationType', $v)) {
            $payload['termination_type'] = trim((string) $v['terminationType']);
        }
        if (array_key_exists('reason', $v)) {
            $payload['reason'] = trim((string) $v['reason']);
        }
        if (array_key_exists('noticeDate', $v)) {
            $payload['notice_date'] = $v['noticeDate'];
        }
        if (array_key_exists('terminationDate', $v)) {
            $payload['termination_date'] = $v['terminationDate'];
        }
        if (array_key_exists('status', $v)) {
            $payload['status'] = $v['status'];
        }
        if (array_key_exists('notes', $v)) {
            $payload['notes'] = $v['notes'] !== null ? trim((string) $v['notes']) : null;
        }

        if ($payload !== []) {
            $t->update($payload);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->whereKey($id)
            ->delete();

        return response()->json(['success' => true]);
    }

    private function payload(HcmTermination $t): array
    {
        return [
            'id' => $t->id,
            'employee' => $t->user ? ['id' => $t->user->id, 'name' => $t->user->name, 'email' => $t->user->email] : null,
            'department' => $t->department ?? '',
            'terminationType' => $t->termination_type ?? '',
            'reason' => $t->reason ?? '',
            'noticeDate' => $t->notice_date?->toDateString(),
            'terminationDate' => $t->termination_date?->toDateString(),
            'status' => $t->status ?? 'pending',
            'notes' => $t->notes ?? '',
            'createdAt' => $t->created_at?->toIso8601String(),
        ];
    }
}
