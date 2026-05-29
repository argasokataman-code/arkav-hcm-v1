<?php

namespace App\Http\Controllers\Api\Resignation;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use App\Models\HcmResignation;
use App\Models\User;
use App\Notifications\ResignationApprovalRequestedNotification;
use App\Services\ApprovalConfigService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HcmResignationController extends Controller
{
    use ChecksPermissions;

    private const STATUSES = ['pending', 'approved', 'cancelled'];

    public function __construct(
        private readonly ApprovalConfigService $approvalConfigService,
    ) {}

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
        if ($forbidden = $this->ensurePermission($request, 'resignation.view')) {
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

        $query = HcmResignation::query()
            ->where('company_id', $activeCompanyId)
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

    public function show(Request $request, string $id): JsonResponse
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

        $query = HcmResignation::query()
            ->where('company_id', $activeCompanyId)
            ->with(['user:id,uuid,name,email']);
        $this->applyResignationIdentifierScope($query, $id);
        $r = $query->first();
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
        if (! $auth->hasPermissionForCompany('resignation.view', $activeCompanyId) && (int) $auth->id !== (int) $r->user_id) {
            return $this->resignationForbidden();
        }

        return response()->json([
            'success' => true,
            'data' => $this->payload($r),
        ]);
    }

    public function resignationsForUser(Request $request, string $userId): JsonResponse
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

        $resolvedUserId = $this->resolveUserIdFromIdentifier($userId, $activeCompanyId, false);
        if ($resolvedUserId === null) {
            abort(404);
        }

        $auth = $request->user();
        if (! $auth->hasPermissionForCompany('resignation.view', $activeCompanyId) && (int) $auth->id !== $resolvedUserId) {
            return $this->resignationForbidden();
        }

        $v = $request->validate([
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $rows = HcmResignation::query()
            ->where('company_id', $activeCompanyId)
            ->with(['user:id,uuid,name,email'])
            ->where('user_id', $resolvedUserId)
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
        if ($forbidden = $this->ensurePermission($request, 'resignation.manage')) {
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
            'userId' => ['required', 'uuid', 'exists:users,uuid'],
            'department' => ['nullable', 'string', 'max:150'],
            'reason' => ['required', 'string', 'max:2000'],
            'noticeDate' => ['required', 'date'],
            'resignationDate' => ['required', 'date', 'after_or_equal:noticeDate'],
            'status' => ['nullable', 'string', 'in:'.implode(',', self::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

            $resolvedUserId = $this->resolveUserIdFromIdentifier((string) $v['userId'], $activeCompanyId);
            if ($resolvedUserId === null) {
                return $this->invalidActiveCompanyUserResponse();
            }

        $r = HcmResignation::query()->create([
            'company_id' => $activeCompanyId,
                'user_id' => $resolvedUserId,
            'department' => isset($v['department']) ? trim((string) $v['department']) : null,
            'reason' => trim((string) $v['reason']),
            'notice_date' => $v['noticeDate'],
            'resignation_date' => $v['resignationDate'],
            'status' => $v['status'] ?? 'pending',
            'notes' => isset($v['notes']) ? trim((string) $v['notes']) : null,
        ]);

        // Notify configured approvers when resignation enters pending state
        if (($v['status'] ?? 'pending') === 'pending') {
            $approvers = $this->approvalConfigService->resolveApproversToNotify($activeCompanyId, 'resignation');
            foreach ($approvers as $approver) {
                $approver->notify(new ResignationApprovalRequestedNotification($r));
            }
        }

        return response()->json(['success' => true, 'data' => ['id' => $r->id]], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'resignation.manage')) {
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

        $query = HcmResignation::query()->where('company_id', $activeCompanyId);
        $this->applyResignationIdentifierScope($query, $id);
        $r = $query->firstOrFail();

        $v = $request->validate([
            'userId' => ['sometimes', 'required', 'uuid', 'exists:users,uuid'],
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
            $resolvedUserId = $this->resolveUserIdFromIdentifier((string) $v['userId'], $activeCompanyId);
            if ($resolvedUserId === null) {
                return $this->invalidActiveCompanyUserResponse();
            }

            $payload['user_id'] = $resolvedUserId;
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
            // Track who approved and when
            if ($v['status'] === 'approved' && $r->status !== 'approved') {
                $payload['approved_by_user_id'] = $request->user()->id;
                $payload['approved_at'] = now();
            }
        }
        if (array_key_exists('notes', $v)) {
            $payload['notes'] = $v['notes'] !== null ? trim((string) $v['notes']) : null;
        }

        if ($payload !== []) {
            $r->update($payload);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'resignation.manage')) {
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

        $query = HcmResignation::query()->where('company_id', $activeCompanyId);
        $this->applyResignationIdentifierScope($query, $id);
        $query->delete();

        return response()->json(['success' => true]);
    }

    private function applyResignationIdentifierScope(Builder $query, string $identifier): Builder
    {
        if (Str::isUuid($identifier)) {
            return $query->where('uuid', $identifier);
        }

        if (ctype_digit($identifier)) {
            return $query->whereKey((int) $identifier);
        }

        return $query->where('uuid', $identifier);
    }

    private function resolveUserIdFromIdentifier(string $identifier, int $activeCompanyId, bool $mustBelongToCompany = true): ?int
    {
        $userId = 0;
        if (Str::isUuid($identifier)) {
            $userId = (int) (User::query()->where('uuid', $identifier)->value('id') ?? 0);
        } elseif (ctype_digit($identifier)) {
            $userId = (int) $identifier;
        }

        if ($userId <= 0) {
            return null;
        }

        if (! User::query()->whereKey($userId)->exists()) {
            return null;
        }

        if (! $mustBelongToCompany) {
            return $userId;
        }

        return CompanyUser::query()
            ->where('company_id', $activeCompanyId)
            ->where('user_id', $userId)
            ->exists()
            ? $userId
            : null;
    }

    private function invalidActiveCompanyUserResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'The selected user id is invalid for the active company.',
            ],
        ], 422);
    }

    private function payload(HcmResignation $r): array
    {
        return [
            'id' => $r->id,
            'uuid' => $r->uuid,
            'employee' => $r->user ? ['id' => $r->user->id, 'uuid' => $r->user->uuid, 'name' => $r->user->name, 'email' => $r->user->email] : null,
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
