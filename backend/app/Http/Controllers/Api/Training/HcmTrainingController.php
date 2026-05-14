<?php

namespace App\Http\Controllers\Api\Training;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\HcmTraining;
use App\Models\HcmTrainer;
use App\Models\HcmTrainingType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HcmTrainingController extends Controller
{
    use ChecksPermissions;

    private function tenantContextRequired(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TENANT_CONTEXT_REQUIRED',
                'message' => 'Active company context is required.',
            ],
        ], 422);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'AUTH_FORBIDDEN',
                'message' => 'Forbidden.',
            ],
        ], 403);
    }

    // -------------------------
    // Training Types
    // -------------------------
    public function types(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantContextRequired();
        }

        $query = HcmTrainingType::query()->orderBy('name');
        $query->where('company_id', $companyId);
        if (! $this->canManageTraining($request)) {
            $query->where('is_active', true);
        }

        $rows = $query->get()->map(fn (HcmTrainingType $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'description' => $t->description ?? '',
            'isActive' => (bool) $t->is_active,
        ])->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function storeType(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'training.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantContextRequired();
        }

        $v = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        if (HcmTrainingType::query()->where('company_id', $companyId)->where('name', trim((string) $v['name']))->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'name already exists.',
                ],
            ], 422);
        }

        $t = HcmTrainingType::query()->create([
            'company_id' => $companyId,
            'name' => trim((string) $v['name']),
            'description' => isset($v['description']) ? trim((string) $v['description']) : null,
            'is_active' => (bool) ($v['isActive'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $t->id]], 201);
    }

    public function updateType(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'training.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantContextRequired();
        }

        $t = HcmTrainingType::query()->where('company_id', $companyId)->findOrFail($id);
        $v = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $name = trim((string) $v['name']);
        if ($name !== $t->name && HcmTrainingType::query()->where('company_id', $companyId)->where('name', $name)->whereKeyNot($t->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'name already exists.',
                ],
            ], 422);
        }

        $t->update([
            'name' => $name,
            'description' => isset($v['description']) ? trim((string) $v['description']) : null,
            'is_active' => (bool) ($v['isActive'] ?? true),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroyType(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'training.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantContextRequired();
        }

        HcmTrainingType::query()->where('company_id', $companyId)->whereKey($id)->delete();

        return response()->json(['success' => true]);
    }

    // -------------------------
    // Trainings (admin-only, phase 1)
    // -------------------------
    public function trainings(Request $request): JsonResponse
    {
        $canManage = $this->canManageTraining($request);
        if (! $canManage && ! $this->canViewTraining($request)) {
            return $this->forbidden();
        }

        $auth = $request->user();
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantContextRequired();
        }

        $v = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'inactive', 'completed'])],
            'trainingTypeId' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:200'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HcmTraining::query()
            ->with([
                'type:id,name',
                'trainer:id,name,is_active',
                'participants:id,name,email',
            ])
            ->where('company_id', $companyId)
            ->orderByDesc('id');

        if (! $canManage && $auth) {
            $query->whereHas('participants', fn ($participantQuery) => $participantQuery->where('users.id', (int) $auth->id));
        }

        if (! empty($v['status'])) {
            $query->where('status', $v['status']);
        }
        if (! empty($v['trainingTypeId'])) {
            $query->where('training_type_id', (int) $v['trainingTypeId']);
        }
        if (! empty($v['q'])) {
            $q = trim((string) $v['q']);
            $query->where(function ($qq) use ($q): void {
                $qq->where('trainer_name', 'like', '%'.$q.'%')
                    ->orWhereHas('trainer', fn ($trainerQuery) => $trainerQuery->where('name', 'like', '%'.$q.'%'))
                    ->orWhere('description', 'like', '%'.$q.'%');
            });
        }

        $rows = $query->paginate((int) ($v['perPage'] ?? 20));
        $data = $rows->getCollection()->map(fn (HcmTraining $t) => [
            'id' => $t->id,
            'type' => $t->type ? ['id' => $t->type->id, 'name' => $t->type->name] : null,
            'trainerId' => $t->trainer_id ? (int) $t->trainer_id : null,
            'trainerName' => $t->trainer_name ?? '',
            'trainer' => $t->trainer ? [
                'id' => (int) $t->trainer->id,
                'name' => (string) $t->trainer->name,
                'isActive' => (bool) $t->trainer->is_active,
            ] : null,
            'participants' => $t->participants->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->values(),
            'startDate' => $t->start_date?->toDateString(),
            'endDate' => $t->end_date?->toDateString(),
            'description' => $t->description ?? '',
            'costCents' => (int) $t->cost_cents,
            'status' => $t->status,
            'updatedAt' => $t->updated_at?->toIso8601String(),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function storeTraining(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'training.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantContextRequired();
        }

        $v = $request->validate([
            'trainingTypeId' => ['nullable', 'integer', Rule::exists('hcm_training_types', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'trainerId' => ['nullable', 'integer', Rule::exists('hcm_trainers', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'trainerName' => ['nullable', 'string', 'max:200'],
            'participantUserIds' => ['nullable', 'array', 'max:200'],
            'participantUserIds.*' => ['integer', Rule::exists('company_users', 'user_id')->where(fn ($query) => $query->where('company_id', $companyId)->where('status', 'active'))],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'description' => ['nullable', 'string', 'max:5000'],
            'costCents' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'completed'])],
        ]);

        $trainerData = $this->resolveTrainerInput($v + ['__companyId' => $companyId], false);

        $t = HcmTraining::query()->create([
            'company_id' => $companyId,
            'training_type_id' => $v['trainingTypeId'] ?? null,
            'trainer_id' => $trainerData['trainer_id'],
            'trainer_name' => $trainerData['trainer_name'],
            'start_date' => $v['startDate'] ?? null,
            'end_date' => $v['endDate'] ?? null,
            'description' => isset($v['description']) ? trim((string) $v['description']) : null,
            'cost_cents' => (int) ($v['costCents'] ?? 0),
            'status' => (string) ($v['status'] ?? 'active'),
        ]);

        if (! empty($v['participantUserIds'])) {
            $t->participants()->sync(array_values(array_unique($v['participantUserIds'])));
        }

        return response()->json(['success' => true, 'data' => ['id' => $t->id]], 201);
    }

    public function updateTraining(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'training.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantContextRequired();
        }

        $t = HcmTraining::query()->where('company_id', $companyId)->findOrFail($id);

        $v = $request->validate([
            'trainingTypeId' => ['sometimes', 'nullable', 'integer', Rule::exists('hcm_training_types', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'trainerId' => ['sometimes', 'nullable', 'integer', Rule::exists('hcm_trainers', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'trainerName' => ['sometimes', 'nullable', 'string', 'max:200'],
            'participantUserIds' => ['sometimes', 'nullable', 'array', 'max:200'],
            'participantUserIds.*' => ['integer', Rule::exists('company_users', 'user_id')->where(fn ($query) => $query->where('company_id', $companyId)->where('status', 'active'))],
            'startDate' => ['sometimes', 'required', 'date'],
            'endDate' => ['sometimes', 'required', 'date', 'after_or_equal:startDate'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'costCents' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000000'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive', 'completed'])],
        ]);

        $data = [];
        if (array_key_exists('trainingTypeId', $v)) {
            $data['training_type_id'] = $v['trainingTypeId'];
        }

        $trainerData = $this->resolveTrainerInput($v + ['__companyId' => $companyId], true);
        if (array_key_exists('trainer_id', $trainerData)) {
            $data['trainer_id'] = $trainerData['trainer_id'];
            $data['trainer_name'] = $trainerData['trainer_name'];
        }
        if (array_key_exists('startDate', $v)) {
            $data['start_date'] = $v['startDate'];
        }
        if (array_key_exists('endDate', $v)) {
            $data['end_date'] = $v['endDate'];
        }
        if (array_key_exists('description', $v)) {
            $data['description'] = $v['description'] !== null ? trim((string) $v['description']) : null;
        }
        if (array_key_exists('costCents', $v)) {
            $data['cost_cents'] = (int) ($v['costCents'] ?? 0);
        }
        if (array_key_exists('status', $v)) {
            $data['status'] = (string) ($v['status'] ?? $t->status);
        }

        if (! empty($data)) {
            $t->update($data);
        }

        if (array_key_exists('participantUserIds', $v)) {
            $ids = $v['participantUserIds'] ?? [];
            $t->participants()->sync(array_values(array_unique($ids)));
        }

        return response()->json(['success' => true]);
    }

    public function destroyTraining(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'training.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantContextRequired();
        }

        HcmTraining::query()->where('company_id', $companyId)->whereKey($id)->delete();

        return response()->json(['success' => true]);
    }

    // Training list for an employee (admin: any; employee: self)
    public function trainingsForUser(Request $request, int $userId): JsonResponse
    {
        $auth = $request->user();
        $isAdmin = $this->canManageTraining($request);
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantContextRequired();
        }

        if (! $isAdmin && (int) $auth->id !== (int) $userId) {
            return $this->forbidden();
        }

        $isCompanyMember = \App\Models\CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $isCompanyMember) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'User not found in active company.',
                ],
            ], 404);
        }

        $v = $request->validate([
            'perPage' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = HcmTraining::query()
            ->with(['type:id,name', 'trainer:id,name,is_active'])
            ->where('company_id', $companyId)
            ->whereHas('participants', fn ($q) => $q->where('users.id', $userId))
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        $rows = $query->paginate((int) ($v['perPage'] ?? 20));
        $data = $rows->getCollection()->map(fn (HcmTraining $t) => [
            'id' => $t->id,
            'type' => $t->type ? ['id' => $t->type->id, 'name' => $t->type->name] : null,
            'trainerId' => $t->trainer_id ? (int) $t->trainer_id : null,
            'trainerName' => $t->trainer_name ?? '',
            'trainer' => $t->trainer ? [
                'id' => (int) $t->trainer->id,
                'name' => (string) $t->trainer->name,
                'isActive' => (bool) $t->trainer->is_active,
            ] : null,
            'startDate' => $t->start_date?->toDateString(),
            'endDate' => $t->end_date?->toDateString(),
            'description' => $t->description ?? '',
            'status' => $t->status,
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    // -------------------------
    // Trainers (Phase 1)
    // -------------------------
    public function trainers(Request $request): JsonResponse
    {
        if (! $this->canManageTraining($request)) {
            return $this->forbidden();
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantContextRequired();
        }

        $v = $request->validate([
            'status' => ['nullable', 'in:active,inactive'],
            'q' => ['nullable', 'string', 'max:200'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HcmTrainer::query()->where('company_id', $companyId)->orderBy('name');
        if (! empty($v['status'])) {
            $query->where('is_active', $v['status'] === 'active');
        }
        if (! empty($v['q'])) {
            $q = trim((string) $v['q']);
            $query->where(function ($qq) use ($q): void {
                $qq->where('name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%');
            });
        }

        $rows = $query->paginate((int) ($v['perPage'] ?? 20));
        $data = $rows->getCollection()->map(fn (HcmTrainer $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'email' => $t->email ?? '',
            'phone' => $t->phone ?? '',
            'description' => $t->description ?? '',
            'isActive' => (bool) $t->is_active,
            'updatedAt' => $t->updated_at?->toIso8601String(),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function storeTrainer(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'training.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantContextRequired();
        }

        $v = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $t = HcmTrainer::query()->create([
            'company_id' => $companyId,
            'name' => trim((string) $v['name']),
            'email' => isset($v['email']) ? trim((string) $v['email']) : null,
            'phone' => isset($v['phone']) ? trim((string) $v['phone']) : null,
            'description' => isset($v['description']) ? trim((string) $v['description']) : null,
            'is_active' => (bool) ($v['isActive'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $t->id]], 201);
    }

    public function updateTrainer(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'training.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantContextRequired();
        }

        $t = HcmTrainer::query()->where('company_id', $companyId)->findOrFail($id);
        $v = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $t->update([
            'name' => trim((string) $v['name']),
            'email' => isset($v['email']) ? trim((string) $v['email']) : null,
            'phone' => isset($v['phone']) ? trim((string) $v['phone']) : null,
            'description' => isset($v['description']) ? trim((string) $v['description']) : null,
            'is_active' => (bool) ($v['isActive'] ?? true),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroyTrainer(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'training.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantContextRequired();
        }

        HcmTrainer::query()->where('company_id', $companyId)->whereKey($id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array{trainer_id?:int|null,trainer_name?:string|null}
     */
    private function resolveTrainerInput(array $validated, bool $partialUpdate): array
    {
        $companyId = $validated['__companyId'] ?? null;
        $hasTrainerId = array_key_exists('trainerId', $validated);
        $hasTrainerName = array_key_exists('trainerName', $validated);

        if (! $hasTrainerId && ! $hasTrainerName) {
            return $partialUpdate ? [] : ['trainer_id' => null, 'trainer_name' => null];
        }

        if ($hasTrainerId && $validated['trainerId'] !== null) {
            $trainerQuery = HcmTrainer::query();
            if ($companyId) {
                $trainerQuery->where('company_id', $companyId);
            }
            $trainer = $trainerQuery->find((int) $validated['trainerId']);

            if ($trainer) {
                return [
                    'trainer_id' => (int) $trainer->id,
                    'trainer_name' => (string) $trainer->name,
                ];
            }
        }

        if ($hasTrainerName) {
            $name = $validated['trainerName'] !== null ? trim((string) $validated['trainerName']) : null;
            if ($name === null || $name === '') {
                return ['trainer_id' => null, 'trainer_name' => null];
            }

            $matchedTrainer = HcmTrainer::query()
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->where('name', $name)
                ->first();

            return [
                'trainer_id' => $matchedTrainer ? (int) $matchedTrainer->id : null,
                'trainer_name' => $name,
            ];
        }

        return ['trainer_id' => null, 'trainer_name' => null];
    }

    private function canManageTraining(Request $request): bool
    {
        return $this->hasPermission($request, 'training.manage');
    }

    private function canViewTraining(Request $request): bool
    {
        return $this->hasPermission($request, 'training.view') || $this->canManageTraining($request);
    }
}

