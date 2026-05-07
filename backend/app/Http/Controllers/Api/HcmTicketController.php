<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use App\Models\Ticket;
use App\Models\TicketAssignmentHistory;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketResolvedNotification;
use App\Notifications\TicketClosedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HcmTicketController extends Controller
{
    private function canManageTickets(Request $request): bool
    {
        $user = $request->user();
        $companyId = $this->activeCompanyId($request);
        if (! $user || ! $companyId) {
            return false;
        }

        if ($user->isGlobalHcmAdmin()) {
            return true;
        }

        if ($user->isHcmAdminForCompany($companyId)) {
            return true;
        }

        return $user->hasPermissionForCompany('ticket.assign', $companyId)
            || $user->hasPermissionForCompany('ticket.update', $companyId)
            || $user->hasPermissionForCompany('ticket.category.manage', $companyId)
            || $user->hasPermissionForCompany('tickets.manage', $companyId)
            || $user->hasPermissionForCompany('ticket.admin', $companyId);
    }

    /**
     * Block tenants whose active subscription does not include the `tickets`
     * feature. Tenants without an active subscription are allowed to pass
     * through so trial / pending_payment flows remain intact (the page-level
     * middleware already redirects pending_payment companies to /subscription).
     */
    private function ensureTicketsFeatureOrFail(int $companyId): ?JsonResponse
    {
        // Global Super Admin always has access; subscription-level feature
        // gating does not apply to the platform maintainer.
        if (request()?->user()?->isGlobalHcmAdmin()) {
            return null;
        }

        if ($companyId <= 0) {
            return null;
        }

        $subscription = \App\Models\Subscription::activeForCompany($companyId);
        if ($subscription && ! $subscription->package?->hasFeature('tickets')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUBSCRIPTION_REQUIRED',
                    'message' => 'Ticket feature requires an active subscription. Please upgrade your plan.',
                ],
            ], 403);
        }

        return null;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $this->canManageTickets($request);
        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        if ($block = $this->ensureTicketsFeatureOrFail($activeCompanyId)) {
            return $block;
        }

        $validated = $request->validate([
            'status' => ['nullable', 'in:open,in_progress,resolved,closed'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'q' => ['nullable', 'string', 'max:120'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Ticket::query()
            ->with(['reporter:id,name,email', 'assignee:id,name,email'])
            ->withCount(['comments', 'attachments']);

        // Tenant isolation: new tickets are scoped by company_id; legacy rows fall back
        // to reporter membership until all historical records are backfilled.
        $query->where(function ($builder) use ($activeCompanyId): void {
            $builder->where('company_id', $activeCompanyId)
                ->orWhere(function ($legacy) use ($activeCompanyId): void {
                    $legacy->whereNull('company_id')
                        ->whereHas('reporter.companyMemberships', function ($membership) use ($activeCompanyId): void {
                            $membership->where('company_id', $activeCompanyId)->where('status', 'active');
                        });
                });
        });

        if (! $isAdmin) {
            $query->where('user_id', $user?->id);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }
        if (! empty($validated['q'])) {
            $q = trim((string) $validated['q']);
            $query->where(function ($builder) use ($q): void {
                $builder->where('subject', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderByDesc('updated_at')->paginate((int) ($validated['perPage'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => $rows->getCollection()->map(fn (Ticket $t) => $this->ticketList($t))->values(),
            'meta' => [
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
                'summary' => $this->summary($activeCompanyId, $isAdmin ? null : (int) $user->id),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $this->canManageTickets($request);
        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:120'],
            'categoryId' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'slaDueAt' => ['nullable', 'date'],
            'assigneeUserId' => ['nullable'],
        ]);

        if (! $isAdmin && ! empty($validated['assigneeUserId'])) {
            return $this->forbidden();
        }

        if ($block = $this->ensureTicketsFeatureOrFail($activeCompanyId)) {
            return $block;
        }

        $resolvedCategory = $this->resolveCategoryInput($validated);
        $assigneeUserId = $this->resolveScopedUserIdentifierOrFail($request, $validated['assigneeUserId'] ?? null);

        $ticket = Ticket::query()->create([
            'company_id' => $activeCompanyId,
            'user_id' => $user->id,
            'code' => $this->generateCode(),
            'subject' => trim((string) $validated['subject']),
            'description' => trim((string) $validated['description']),
            'category' => $resolvedCategory['name'],
            'category_id' => $resolvedCategory['id'],
            'priority' => $validated['priority'],
            'status' => 'open',
            'sla_due_at' => $validated['slaDueAt'] ?? null,
            'assignee_user_id' => $assigneeUserId,
        ]);

        if (! empty($validated['assigneeUserId'])) {
            TicketAssignmentHistory::query()->create([
                'ticket_id' => $ticket->id,
                'actor_user_id' => $user->id,
                'from_assignee_user_id' => null,
                'to_assignee_user_id' => $assigneeUserId,
                'note' => 'Assigned on creation.',
            ]);
        }

        // Notify admin users about the new ticket
        $this->notifyCompanyAdminsTicket($activeCompanyId, new TicketCreatedNotification($ticket->fresh()), $user->id);

        // Notify the assignee if specified
        if ($assigneeUserId !== null) {
            $assignee = User::query()->find($assigneeUserId);
            $assignee?->notify(new TicketAssignedNotification($ticket->fresh()));
        }

        return response()->json(['success' => true, 'data' => ['id' => $ticket->id, 'code' => $ticket->code]], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($block = $this->ensureTicketsFeatureOrFail((int) $this->activeCompanyId($request))) {
            return $block;
        }
        $ticket = $this->authorizedTicket($request, $id);
        if (! $ticket) {
            return $this->forbidden();
        }
        $ticket->load([
            'reporter:id,name,email',
            'assignee:id,name,email',
            'resolver:id,name,email',
            'comments.user:id,name,email',
            'attachments.user:id,name,email',
            'assignmentHistory.actor:id,name,email',
            'assignmentHistory.fromAssignee:id,name,email',
            'assignmentHistory.toAssignee:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->ticketDetail($ticket, $this->canManageTickets($request)),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if ($block = $this->ensureTicketsFeatureOrFail((int) $this->activeCompanyId($request))) {
            return $block;
        }
        $ticket = $this->authorizedTicket($request, $id);
        if (! $ticket) {
            return $this->forbidden();
        }

        $user = $request->user();
        $isAdmin = $this->canManageTickets($request);
        $validated = $request->validate([
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:120'],
            'categoryId' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'priority' => ['sometimes', 'required', 'in:low,medium,high,urgent'],
            'status' => ['sometimes', 'required', 'in:open,in_progress,resolved,closed'],
            'slaDueAt' => ['nullable', 'date'],
            'assigneeUserId' => ['nullable'],
        ]);

        if (! $isAdmin) {
            if (array_key_exists('status', $validated) || array_key_exists('assigneeUserId', $validated) || array_key_exists('slaDueAt', $validated)) {
                return $this->forbidden();
            }
            if ($ticket->status === 'closed') {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'TICKET_CLOSED_LOCKED', 'message' => 'Closed ticket cannot be edited by employee.'],
                ], 422);
            }
        }

        $beforeAssignee = $ticket->assignee_user_id;
        $beforeStatus = $ticket->status;

        if (array_key_exists('subject', $validated)) {
            $ticket->subject = trim((string) $validated['subject']);
        }
        if (array_key_exists('description', $validated)) {
            $ticket->description = trim((string) $validated['description']);
        }
        if (array_key_exists('categoryId', $validated) || array_key_exists('category', $validated)) {
            $resolvedCategory = $this->resolveCategoryInput($validated);
            $ticket->category_id = $resolvedCategory['id'];
            $ticket->category = $resolvedCategory['name'];
        }
        if (array_key_exists('priority', $validated)) {
            $ticket->priority = $validated['priority'];
        }
        if ($isAdmin && array_key_exists('slaDueAt', $validated)) {
            $ticket->sla_due_at = $validated['slaDueAt'] ?? null;
        }
        if ($isAdmin && array_key_exists('assigneeUserId', $validated)) {
            $ticket->assignee_user_id = $this->resolveScopedUserIdentifierOrFail($request, $validated['assigneeUserId'] ?? null);
        }
        if ($isAdmin && array_key_exists('status', $validated)) {
            $ticket->status = $validated['status'];
            if ($ticket->status === 'resolved' && $beforeStatus !== 'resolved') {
                $ticket->resolved_at = now();
                $ticket->resolver_user_id = $user->id;
            }
            if ($ticket->status === 'closed' && $beforeStatus !== 'closed') {
                $ticket->closed_at = now();
            }
            if ($ticket->status === 'open') {
                $ticket->resolved_at = null;
                $ticket->resolver_user_id = null;
                $ticket->closed_at = null;
            }
        }
        $ticket->save();

        if ($isAdmin && array_key_exists('assigneeUserId', $validated) && $beforeAssignee !== $ticket->assignee_user_id) {
            TicketAssignmentHistory::query()->create([
                'ticket_id' => $ticket->id,
                'actor_user_id' => $user->id,
                'from_assignee_user_id' => $beforeAssignee,
                'to_assignee_user_id' => $ticket->assignee_user_id,
                'note' => 'Assignment changed.',
            ]);

            // Notify new assignee
            if ($ticket->assignee_user_id !== null) {
                $newAssignee = User::query()->find($ticket->assignee_user_id);
                $newAssignee?->notify(new TicketAssignedNotification($ticket->fresh()));
            }
        }

        // Notify reporter when ticket status changes
        if ($isAdmin && array_key_exists('status', $validated)) {
            $reporter = $ticket->reporter;
            if ($reporter !== null) {
                if ($ticket->status === 'resolved' && $beforeStatus !== 'resolved') {
                    $reporter->notify(new TicketResolvedNotification($ticket->fresh()));
                } elseif ($ticket->status === 'closed' && $beforeStatus !== 'closed') {
                    $reporter->notify(new TicketClosedNotification($ticket->fresh()));
                }
            }
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($block = $this->ensureTicketsFeatureOrFail((int) $this->activeCompanyId($request))) {
            return $block;
        }
        $ticket = $this->authorizedTicket($request, $id);
        if (! $ticket) {
            return $this->forbidden();
        }
        if (! $this->canManageTickets($request) && $ticket->status === 'closed') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TICKET_CLOSED_LOCKED', 'message' => 'Closed ticket cannot be deleted by employee.'],
            ], 422);
        }
        $ticket->delete();
        return response()->json(['success' => true]);
    }

    public function addComment(Request $request, string $id): JsonResponse
    {
        if ($block = $this->ensureTicketsFeatureOrFail((int) $this->activeCompanyId($request))) {
            return $block;
        }
        $ticket = $this->authorizedTicket($request, $id);
        if (! $ticket) {
            return $this->forbidden();
        }
        if (! $this->canManageTickets($request) && $ticket->status === 'closed') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TICKET_CLOSED_LOCKED', 'message' => 'Closed ticket cannot be commented by employee.'],
            ], 422);
        }
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $comment = TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => trim((string) $validated['body']),
        ]);
        return response()->json(['success' => true, 'data' => ['id' => $comment->id]], 201);
    }

    public function addAttachment(Request $request, string $id): JsonResponse
    {
        if ($block = $this->ensureTicketsFeatureOrFail((int) $this->activeCompanyId($request))) {
            return $block;
        }
        $ticket = $this->authorizedTicket($request, $id);
        if (! $ticket) {
            return $this->forbidden();
        }
        if (! $this->canManageTickets($request) && $ticket->status === 'closed') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TICKET_CLOSED_LOCKED', 'message' => 'Closed ticket cannot receive attachments from employee.'],
            ], 422);
        }
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,csv,txt'],
        ]);
        $file = $validated['file'];
        $path = $file->store("tickets/{$ticket->id}", 'public');
        $attachment = TicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => (string) $file->getClientOriginalName(),
            'mime_type' => (string) $file->getClientMimeType(),
            'size_bytes' => (int) $file->getSize(),
        ]);
        return response()->json(['success' => true, 'data' => ['id' => $attachment->id]], 201);
    }

    public function downloadAttachment(Request $request, string $id, int $attachmentId)
    {
        $ticket = $this->authorizedTicket($request, $id);
        if (! $ticket) {
            return $this->forbidden();
        }
        $attachment = TicketAttachment::query()->where('ticket_id', $ticket->id)->whereKey($attachmentId)->firstOrFail();
        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            return response()->json(['success' => false, 'error' => ['code' => 'FILE_NOT_FOUND', 'message' => 'Attachment file is not found.']], 404);
        }
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function previewAttachment(Request $request, string $id, int $attachmentId)
    {
        $ticket = $this->authorizedTicket($request, $id);
        if (! $ticket) {
            return $this->forbidden();
        }
        $attachment = TicketAttachment::query()->where('ticket_id', $ticket->id)->whereKey($attachmentId)->firstOrFail();
        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            return response()->json(['success' => false, 'error' => ['code' => 'FILE_NOT_FOUND', 'message' => 'Attachment file is not found.']], 404);
        }

        $absolutePath = Storage::disk($attachment->disk)->path($attachment->path);
        return response()->file($absolutePath, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"',
        ]);
    }

    public function assignableUsers(Request $request): JsonResponse
    {
        if (! $this->canManageTickets($request)) {
            return $this->forbidden();
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $rows = User::query()
            ->whereHas('companyMemberships', function ($membership) use ($activeCompanyId): void {
                $membership->where('company_id', $activeCompanyId)
                    ->where('status', 'active');
            })
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values(),
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        if (! $this->canManageTickets($request)) {
            return $this->forbidden();
        }
        $rows = TicketCategory::query()->orderBy('sort_order')->orderBy('name')->get();
        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (TicketCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'isActive' => (bool) $c->is_active,
                'sortOrder' => (int) $c->sort_order,
            ])->values(),
        ]);
    }

    public function categoryOptions(): JsonResponse
    {
        $rows = TicketCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (TicketCategory $c) => ['id' => $c->id, 'name' => $c->name])->values(),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        if (! $this->canManageTickets($request)) {
            return $this->forbidden();
        }
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('ticket_categories', 'name')],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $row = TicketCategory::query()->create([
            'name' => trim((string) $validated['name']),
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ]);
        return response()->json(['success' => true, 'data' => ['id' => $row->id]], 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        if (! $this->canManageTickets($request)) {
            return $this->forbidden();
        }
        $row = TicketCategory::query()->findOrFail($id);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('ticket_categories', 'name')->ignore($id)],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $row->update([
            'name' => trim((string) $validated['name']),
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ]);
        return response()->json(['success' => true]);
    }

    public function destroyCategory(Request $request, int $id): JsonResponse
    {
        if (! $this->canManageTickets($request)) {
            return $this->forbidden();
        }
        TicketCategory::query()->whereKey($id)->delete();
        return response()->json(['success' => true]);
    }

    private function ticketList(Ticket $t): array
    {
        return [
            'id' => $t->id,
            'code' => $t->code,
            'subject' => $t->subject,
            'description' => Str::limit($t->description, 220),
            'category' => $t->category ?? '',
            'categoryId' => $t->category_id ? (int) $t->category_id : null,
            'priority' => $t->priority,
            'status' => $t->status,
            'slaDueAt' => $t->sla_due_at?->toIso8601String(),
            'reporter' => $t->reporter ? ['id' => $t->reporter->id, 'name' => $t->reporter->name, 'email' => $t->reporter->email] : null,
            'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name, 'email' => $t->assignee->email] : null,
            'commentsCount' => (int) ($t->comments_count ?? 0),
            'attachmentsCount' => (int) ($t->attachments_count ?? 0),
            'updatedAt' => $t->updated_at?->toIso8601String(),
            'createdAt' => $t->created_at?->toIso8601String(),
        ];
    }

    private function ticketDetail(Ticket $t, bool $isAdmin): array
    {
        return array_merge($this->ticketList($t), [
            'description' => $t->description,
            'resolvedAt' => $t->resolved_at?->toIso8601String(),
            'closedAt' => $t->closed_at?->toIso8601String(),
            'resolver' => $t->resolver ? ['id' => $t->resolver->id, 'name' => $t->resolver->name, 'email' => $t->resolver->email] : null,
            'comments' => $t->comments->sortBy('created_at')->values()->map(fn (TicketComment $c) => [
                'id' => $c->id,
                'body' => $c->body,
                'createdAt' => $c->created_at?->toIso8601String(),
                'user' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name, 'email' => $c->user->email] : null,
            ])->all(),
            'attachments' => $t->attachments->sortByDesc('id')->values()->map(fn (TicketAttachment $a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'mimeType' => $a->mime_type,
                'sizeBytes' => (int) $a->size_bytes,
                'uploadedAt' => $a->created_at?->toIso8601String(),
                'uploader' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
                'downloadUrl' => "/v1/hcm/tickets/{$t->id}/attachments/{$a->id}/download",
                'previewUrl' => "/v1/hcm/tickets/{$t->id}/attachments/{$a->id}/preview",
            ])->all(),
            'assignmentHistory' => $t->assignmentHistory->sortByDesc('id')->values()->map(fn (TicketAssignmentHistory $h) => [
                'id' => $h->id,
                'createdAt' => $h->created_at?->toIso8601String(),
                'note' => $h->note ?? '',
                'actor' => $h->actor ? ['id' => $h->actor->id, 'name' => $h->actor->name] : null,
                'fromAssignee' => $h->fromAssignee ? ['id' => $h->fromAssignee->id, 'name' => $h->fromAssignee->name] : null,
                'toAssignee' => $h->toAssignee ? ['id' => $h->toAssignee->id, 'name' => $h->toAssignee->name] : null,
            ])->all(),
            'permissions' => [
                'canManage' => $isAdmin,
                'canEdit' => $isAdmin || $t->status !== 'closed',
                'canDelete' => $isAdmin || $t->status !== 'closed',
            ],
        ]);
    }

    private function summary(int $activeCompanyId, ?int $ownerUserId): array
    {
        $base = Ticket::query();
        $base->where(function ($query) use ($activeCompanyId): void {
            $query->where('company_id', $activeCompanyId)
                ->orWhere(function ($legacy) use ($activeCompanyId): void {
                    $legacy->whereNull('company_id')
                        ->whereHas('reporter.companyMemberships', function ($membership) use ($activeCompanyId): void {
                            $membership->where('company_id', $activeCompanyId)->where('status', 'active');
                        });
                });
        });
        if ($ownerUserId !== null) {
            $base->where('user_id', $ownerUserId);
        }
        $rows = $base->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        return [
            'total' => (int) $rows->sum(),
            'open' => (int) ($rows['open'] ?? 0),
            'inProgress' => (int) ($rows['in_progress'] ?? 0),
            'resolved' => (int) ($rows['resolved'] ?? 0),
            'closed' => (int) ($rows['closed'] ?? 0),
        ];
    }

    private function authorizedTicket(Request $request, string $id): ?Ticket
    {
        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return null;
        }

        $query = Ticket::query()->where(function ($builder) use ($id): void {
            $builder->where('uuid', $id);

            if (ctype_digit($id)) {
                $builder->orWhere('id', (int) $id);
            }
        });
        $query->where(function ($builder) use ($activeCompanyId): void {
            $builder->where('company_id', $activeCompanyId)
                ->orWhere(function ($legacy) use ($activeCompanyId): void {
                    $legacy->whereNull('company_id')
                        ->whereHas('reporter.companyMemberships', function ($membership) use ($activeCompanyId): void {
                            $membership->where('company_id', $activeCompanyId)->where('status', 'active');
                        });
                });
        });

        if (! $this->canManageTickets($request)) {
            $query->where('user_id', $request->user()?->id);
        }
        return $query->first();
    }

    private function userBelongsToActiveCompany(int $userId, ?int $companyId): bool
    {
        if (! $companyId) {
            return true;
        }

        return DB::table('company_users')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->exists();
    }

    private function resolveUserIdentifier(mixed $identifier): ?int
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        $raw = trim((string) $identifier);
        if ($raw === '') {
            return null;
        }

        $resolved = User::query()
            ->where(function (Builder $query) use ($raw): void {
                if (ctype_digit($raw)) {
                    $query->where('id', (int) $raw)
                        ->orWhere('uuid', $raw);

                    return;
                }

                $query->where('uuid', $raw);
            })
            ->value('id');

        return $resolved !== null ? (int) $resolved : null;
    }

    private function resolveScopedUserIdentifierOrFail(Request $request, mixed $identifier): ?int
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        $resolved = $this->resolveUserIdentifier($identifier);
        if (! $resolved) {
            throw ValidationException::withMessages([
                'assigneeUserId' => ['The selected assignee user id is invalid.'],
            ]);
        }

        if (! $this->userBelongsToActiveCompany($resolved, $this->activeCompanyId($request))) {
            throw ValidationException::withMessages([
                'assigneeUserId' => ['The selected assignee user id is invalid for the active company.'],
            ]);
        }

        return $resolved;
    }

    private function generateCode(): string
    {
        $today = now()->format('Ymd');
        $count = (int) Ticket::query()->whereDate('created_at', now()->toDateString())->count() + 1;
        return sprintf('TIC-%s-%03d', $today, $count);
    }

    /**
     * @return array{id:int|null,name:string|null}
     */
    private function resolveCategoryInput(array $validated): array
    {
        if (array_key_exists('categoryId', $validated) && $validated['categoryId'] !== null) {
            $category = TicketCategory::query()->find((int) $validated['categoryId']);
            if ($category) {
                return [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                ];
            }
        }

        if (array_key_exists('category', $validated)) {
            $name = $validated['category'] !== null ? trim((string) $validated['category']) : null;
            if ($name === null || $name === '') {
                return ['id' => null, 'name' => null];
            }

            $matchedCategory = TicketCategory::query()->where('name', $name)->first();

            return [
                'id' => $matchedCategory ? (int) $matchedCategory->id : null,
                'name' => $name,
            ];
        }

        return ['id' => null, 'name' => null];
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'AUTH_FORBIDDEN', 'message' => 'Forbidden.'],
        ], 403);
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Dispatch a notification to all active owner/admin users of a company,
     * excluding the actor user (e.g. the ticket reporter).
     */
    private function notifyCompanyAdminsTicket(?int $companyId, object $notification, ?int $excludeUserId = null): void
    {
        if ($companyId === null || $companyId <= 0) {
            return;
        }

        $adminIds = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->pluck('user_id')
            ->reject(fn ($id) => $excludeUserId !== null && (int) $id === $excludeUserId);

        if ($adminIds->isEmpty()) {
            return;
        }

        User::query()->whereIn('id', $adminIds)->each(function (User $admin) use ($notification): void {
            try {
                $admin->notify(clone $notification);
            } catch (\Throwable) {
                // best-effort
            }
        });
    }
}
