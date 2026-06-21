<?php

namespace App\Http\Controllers\Api\Notes;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmNoteController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        $userId = $request->user()->id;

        $tab = $request->query('tab', 'all'); // all | important | trash

        $query = Note::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId);

        if ($tab === 'important') {
            $query->where('is_important', true)->where('is_trashed', false);
        } elseif ($tab === 'trash') {
            $query->where('is_trashed', true);
        } else {
            $query->where('is_trashed', false);
        }

        $notes = $query->orderByDesc('updated_at')->get()->map(fn (Note $n) => $this->formatNote($n));

        return response()->json(['success' => true, 'data' => $notes->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        $userId = $request->user()->id;

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:300'],
            'content' => ['nullable', 'string', 'max:10000'],
            'tag' => ['nullable', 'in:personal,social,work,others'],
            'priority' => ['nullable', 'in:low,medium,high'],
        ]);

        $note = Note::query()->create([
            'user_id' => $userId,
            'company_id' => $companyId,
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            'tag' => $validated['tag'] ?? 'personal',
            'priority' => $validated['priority'] ?? 'medium',
            'is_important' => false,
            'is_trashed' => false,
        ]);

        return response()->json(['success' => true, 'data' => $this->formatNote($note)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        $userId = $request->user()->id;

        $note = Note::query()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:300'],
            'content' => ['nullable', 'string', 'max:10000'],
            'tag' => ['sometimes', 'in:personal,social,work,others'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'is_important' => ['sometimes', 'boolean'],
            'is_trashed' => ['sometimes', 'boolean'],
        ]);

        $note->update($validated);

        return response()->json(['success' => true, 'data' => $this->formatNote($note->fresh())]);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        $userId = $request->user()->id;

        $note = Note::query()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $note->delete();

        return response()->json(['success' => true]);
    }

    private function formatNote(Note $n): array
    {
        return [
            'id' => $n->id,
            'uuid' => $n->uuid,
            'title' => $n->title,
            'content' => $n->content ?? '',
            'tag' => $n->tag,
            'priority' => $n->priority,
            'isImportant' => (bool) $n->is_important,
            'isTrashed' => (bool) $n->is_trashed,
            'updatedAt' => $n->updated_at?->toIso8601String(),
            'createdAt' => $n->created_at?->toIso8601String(),
        ];
    }
}
