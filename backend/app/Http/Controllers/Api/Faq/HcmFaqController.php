<?php

namespace App\Http\Controllers\Api\Faq;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmFaqController extends Controller
{
    use EnsuresHcmAdmin;

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if ($companyId <= 0) {
            return $this->tenantContextRequired();
        }

        if ($block = $this->ensureHcmAdminForCompany($request, $companyId)) {
            return $block;
        }

        $entries = Faq::query()
            ->where('company_id', $companyId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Faq $faq): array => $this->formatFaq($faq));

        return response()->json([
            'success' => true,
            'data' => $entries->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if ($companyId <= 0) {
            return $this->tenantContextRequired();
        }

        if ($block = $this->ensureHcmAdminForCompany($request, $companyId)) {
            return $block;
        }

        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:10000'],
        ]);

        $userId = (int) $request->user()->id;
        $faq = Faq::query()->create([
            'company_id' => $companyId,
            'category' => $validated['category'],
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatFaq($faq),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if ($companyId <= 0) {
            return $this->tenantContextRequired();
        }

        if ($block = $this->ensureHcmAdminForCompany($request, $companyId)) {
            return $block;
        }

        $faq = Faq::query()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $validated = $request->validate([
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'question' => ['sometimes', 'required', 'string', 'max:500'],
            'answer' => ['sometimes', 'required', 'string', 'max:10000'],
        ]);

        $validated['updated_by'] = (int) $request->user()->id;
        $faq->update($validated);

        return response()->json([
            'success' => true,
            'data' => $this->formatFaq($faq->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if ($companyId <= 0) {
            return $this->tenantContextRequired();
        }

        if ($block = $this->ensureHcmAdminForCompany($request, $companyId)) {
            return $block;
        }

        $faq = Faq::query()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $faq->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if ($companyId <= 0) {
            return $this->tenantContextRequired();
        }

        if ($block = $this->ensureHcmAdminForCompany($request, $companyId)) {
            return $block;
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique($validated['ids']));

        $deleted = Faq::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'deletedCount' => $deleted,
            ],
        ]);
    }

    private function activeCompanyId(Request $request): int
    {
        return (int) ($request->attributes->get('activeCompanyId') ?? 0);
    }

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

    private function formatFaq(Faq $faq): array
    {
        return [
            'id' => $faq->id,
            'uuid' => $faq->uuid,
            'category' => $faq->category,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'createdBy' => $faq->created_by,
            'updatedBy' => $faq->updated_by,
            'updatedAt' => $faq->updated_at?->toIso8601String(),
            'createdAt' => $faq->created_at?->toIso8601String(),
        ];
    }
}
