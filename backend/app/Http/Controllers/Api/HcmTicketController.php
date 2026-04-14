<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAssignmentHistory;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HcmTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = (bool) ($user?->isHcmAdmin());
        $validated = $request->validate([
            'status' => ['nullable', 'in:open,in_progress,resolved,closed'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'q' => ['nullable', 'string', 'max:120'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Ticket::query()
            ->with(['reporter:id,name,email', 'assignee:id,name,email'])
            ->withCount(['comments', 'attachments']);

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
                'summary' => $this->summary($isAdmin ? null : (int) $user->id),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = (bool) $user?->isHcmAdmin();
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:120'],
            'categoryId' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'slaDueAt' => ['nullable', 'date'],
            'assigneeUserId' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (! $isAdmin && ! empty($validated['assigneeUserId'])) {
            return $this->forbidden();
        }

        $resolvedCategory = $this->resolveCategoryInput($validated);

        $ticket = Ticket::query()->create([
            'user_id' => $user->id,
            'code' => $this->generateCode(),
            'subject' => trim((string) $validated['subject']),
            'description' => trim((string) $validated['description']),
            'category' => $resolvedCategory['name'],
            'category_id' => $resolvedCategory['id'],
            'priority' => $validated['priority'],
            'status' => 'open',
            'sla_due_at' => $validated['slaDueAt'] ?? null,
            'assignee_user_id' => $validated['assigneeUserId'] ?? null,
        ]);

        if (! empty($validated['assigneeUserId'])) {
            TicketAssignmentHistory::query()->create([
                'ticket_id' => $ticket->id,
                'actor_user_id' => $user->id,
                'from_assignee_user_id' => null,
                'to_assignee_user_id' => (int) $validated['assigneeUserId'],
                'note' => 'Assigned on creation.',
            ]);
        }

        return response()->json(['success' => true, 'data' => ['id' => $ticket->id, 'code' => $ticket->code]], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
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
            'data' => $this->ticketDetail($ticket, (bool) $request->user()?->isHcmAdmin()),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ticket = $this->authorizedTicket($request, $id);
        if (! $ticket) {
            return $this->forbidden();
        }

        $user = $request->user();
        $isAdmin = (bool) $user?->isHcmAdmin();
        $validated = $request->validate([
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:120'],
            'categoryId' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'priority' => ['sometimes', 'required', 'in:low,medium,high,urgent'],
            'status' => ['sometimes', 'required', 'in:open,in_progress,resolved,closed'],
            'slaDueAt' => ['nullable', 'date'],
            'assigneeUserId' => ['nullable', 'integer', 'exists:users,id'],
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
            $ticket->assignee_user_id = $validated['assigneeUserId'] ?? null;
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
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ticket = $this->authorizedTicket($request, $id);
        if (! $ticket) {
            return $this->forbidden();
        }
        if (! $request->user()?->isHcmAdmin() && $ticket->status === 'closed') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TICKET_CLOSED_LOCKED', 'message' => 'Closed ticket cannot be deleted by employee.'],
            ], 422);
        }
        $ticket->delete();
        return response()->json(['success' => true]);
    }

    public function addComment(Request $request, int $id): JsonResponse
    {
        $ticket = $this->authorizedTicket($request, $id);
        if (! $ticket) {
            return $this->forbidden();
        }
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $comment = TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => trim((string) $validated['body']),
        ]);
        return response()->json(['success' => true, 'data' => ['id' => $comment->id]], 201);
    }

    public function addAttachment(Request $request, int $id): JsonResponse
    {
        $ticket = $this->authorizedTicket($request, $id);
        if (! $ticket) {
            return $this->forbidden();
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

    public function downloadAttachment(Request $request, int $id, int $attachmentId)
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

    public function previewAttachment(Request $request, int $id, int $attachmentId)
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
        if (! $request->user()?->isHcmAdmin()) {
            return $this->forbidden();
        }
        $rows = User::query()->orderBy('name')->limit(200)->get(['id', 'name', 'email']);
        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values(),
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        if (! $request->user()?->isHcmAdmin()) {
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
        if (! $request->user()?->isHcmAdmin()) {
            return $this->forbidden();
        }
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:ticket_categories,name'],
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
        if (! $request->user()?->isHcmAdmin()) {
            return $this->forbidden();
        }
        $row = TicketCategory::query()->findOrFail($id);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:ticket_categories,name,'.$id],
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
        if (! $request->user()?->isHcmAdmin()) {
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

    private function summary(?int $ownerUserId): array
    {
        $base = Ticket::query();
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

    private function authorizedTicket(Request $request, int $id): ?Ticket
    {
        $query = Ticket::query()->whereKey($id);
        if (! $request->user()?->isHcmAdmin()) {
            $query->where('user_id', $request->user()?->id);
        }
        return $query->first();
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
}
