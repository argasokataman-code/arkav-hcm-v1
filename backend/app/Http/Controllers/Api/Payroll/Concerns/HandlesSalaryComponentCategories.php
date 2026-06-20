<?php

namespace App\Http\Controllers\Api\Payroll\Concerns;

use App\Models\EmployeeBenefit;
use App\Models\EmployeeTaxProfile;
use App\Models\HcmBpjsGovernancePolicy;
use App\Models\HcmEmployeeAllowancePolicy;
use App\Models\HcmEmployeePayrollItemAssignment;
use App\Models\HcmPayrollItem;
use App\Models\HcmSalaryComponent;
use App\Models\HcmSalaryComponentCategory;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait HandlesSalaryComponentCategories
{
public function categories(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $rows = HcmSalaryComponentCategory::query()
            ->orderBy('kind')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (HcmSalaryComponentCategory $c): array {
                return [
                    'id' => (int) $c->id,
                    'kind' => (string) $c->kind,
                    'code' => (string) $c->code,
                    'name' => (string) $c->name,
                    'description' => $c->description,
                    'isSystem' => (bool) $c->is_system,
                    'isActive' => (bool) $c->is_active,
                    'sortOrder' => (int) $c->sort_order,
                    'usageCount' => HcmSalaryComponent::query()
                        ->where('kind', $c->kind)
                        ->where('category', $c->code)
                        ->count(),
                ];
            })
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'CATEGORY_MASTER_READ_ONLY',
                'message' => 'Master kategori bersifat global dan tidak dapat diubah dari tenant.',
            ],
        ], 403);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'CATEGORY_MASTER_READ_ONLY',
                'message' => 'Master kategori bersifat global dan tidak dapat diubah dari tenant.',
            ],
        ], 403);
    }

    public function destroyCategory(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'CATEGORY_MASTER_READ_ONLY',
                'message' => 'Master kategori bersifat global dan tidak dapat diubah dari tenant.',
            ],
        ], 403);
    }

    private function categoryName(string $kind, string $code): string
    {
        return (string) (HcmSalaryComponentCategory::query()
            ->where('kind', $kind)
            ->where('code', $code)
            ->value('name') ?? $code);
    }

    /**
     * @return array<string, true>
     */
}
