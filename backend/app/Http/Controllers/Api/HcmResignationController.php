<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\HcmResignation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmResignationController extends Controller
{
    use EnsuresHcmAdmin;

    private const STATUSES = ['pending', 'approved', 'cancelled'];

    private function resignationForbidden(): JsonResponse
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

        $query = HcmResignation::query()
            ->with(['user:id,name,email'])
            ->orderByDesc('resignation_date')
            ->orderByDesc('id');

        if (! empty($v['q'])) {
            $q = trim((string) $v['q']);
            $query->where(function ($b) use ($q): void {
                $b->where('department', 'like', '%'.$q.'%')
                    ->orWhere('reason', 'like', '%'.$q.'%')
                    ->orWhere('status', 'like', '%'.$q.'%')
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'));
            });
        }
        if (! empty($v['dateFrom'])) {
            $query->whereDate('resignation_date', '>=', $v['dateFrom']);
        }
        if (! empty($v['dateTo'])) {
            $query->whereDate('resignation_date', '<=', $v['dateTo']);
        }

        $rows = $query->paginate((int) ($v['perPage'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => $rows->getCollection()->map(fn (HcmResignation $r) => $this->payload($r))->values(),
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
        $r = HcmResignation::query()->with(['user:id,name,email'])->find($id);
        if (! $r) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RESIGNATION_NOT_FOUND',
                    'message' => 'Resignation not found.',
                ],
            ], 404);
        }

        $auth = $request->user();
        if (! $auth->isHcmAdmin() && (int) $auth->id !== (int) $r->user_id) {
            return $this->resignationForbidden();
        }

        return response()->json([
            'success' => true,
            'data' => $this->payload($r),
        ]);
    }

    public function resignationsForUser(Request $request, int $userId): JsonResponse
    {
        $auth = $request->user();
        if (! $auth->isHcmAdmin() && (int) $auth->id !== (int) $userId) {
            return $this->resignationForbidden();
        }

        User::query()->findOrFail($userId);

        $v = $request->validate([
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $rows = HcmResignation::query()
            ->with(['user:id,name,email'])
            ->where('user_id', $userId)
            ->orderByDesc('resignation_date')
            ->orderByDesc('id')
            ->paginate((int) ($v['perPage'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => $rows->getCollection()->map(fn (HcmResignation $r) => $this->payload($r))->values(),
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
            'reason' => ['required', 'string', 'max:2000'],
            'noticeDate' => ['required', 'date'],
            'resignationDate' => ['required', 'date', 'after_or_equal:noticeDate'],
            'status' => ['nullable', 'string', 'in:'.implode(',', self::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        User::query()->findOrFail((int) $v['userId']);

        $r = HcmResignation::query()->create([
            'user_id' => (int) $v['userId'],
            'department' => isset($v['department']) ? trim((string) $v['department']) : null,
            'reason' => trim((string) $v['reason']),
            'notice_date' => $v['noticeDate'],
            'resignation_date' => $v['resignationDate'],
            'status' => $v['status'] ?? 'pending',
            'notes' => isset($v['notes']) ? trim((string) $v['notes']) : null,
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $r->id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $r = HcmResignation::query()->findOrFail($id);

        $v = $request->validate([
            'userId' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'department' => ['sometimes', 'nullable', 'string', 'max:150'],
            'reason' => ['sometimes', 'required', 'string', 'max:2000'],
            'noticeDate' => ['sometimes', 'required', 'date'],
            'resignationDate' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', 'string', 'in:'.implode(',', self::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (isset($v['noticeDate'], $v['resignationDate'])) {
            if (strtotime((string) $v['resignationDate']) < strtotime((string) $v['noticeDate'])) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The resignation date must be on or after the notice date.',
                    ],
                ], 422);
            }
        } elseif (isset($v['resignationDate'])) {
            if ($r->notice_date && $r->notice_date->gt(Carbon::parse($v['resignationDate']))) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The resignation date must be on or after the notice date.',
                    ],
                ], 422);
            }
        } elseif (isset($v['noticeDate'])) {
            if ($r->resignation_date && Carbon::parse($v['noticeDate'])->gt($r->resignation_date)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The resignation date must be on or after the notice date.',
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
        if (array_key_exists('reason', $v)) {
            $payload['reason'] = trim((string) $v['reason']);
        }
        if (array_key_exists('noticeDate', $v)) {
            $payload['notice_date'] = $v['noticeDate'];
        }
        if (array_key_exists('resignationDate', $v)) {
            $payload['resignation_date'] = $v['resignationDate'];
        }
        if (array_key_exists('status', $v)) {
            $payload['status'] = $v['status'];
        }
        if (array_key_exists('notes', $v)) {
            $payload['notes'] = $v['notes'] !== null ? trim((string) $v['notes']) : null;
        }

        if ($payload !== []) {
            $r->update($payload);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        HcmResignation::query()->whereKey($id)->delete();

        return response()->json(['success' => true]);
    }

    private function payload(HcmResignation $r): array
    {
        return [
            'id' => $r->id,
            'employee' => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name, 'email' => $r->user->email] : null,
            'department' => $r->department ?? '',
            'reason' => $r->reason ?? '',
            'noticeDate' => $r->notice_date?->toDateString(),
            'resignationDate' => $r->resignation_date?->toDateString(),
            'status' => $r->status ?? 'pending',
            'notes' => $r->notes ?? '',
            'createdAt' => $r->created_at?->toIso8601String(),
        ];
    }
}
