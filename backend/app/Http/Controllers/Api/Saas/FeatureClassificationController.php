<?php

namespace App\Http\Controllers\Api\Saas;

use App\Http\Controllers\Controller;
use App\Models\FeatureClassification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class FeatureClassificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json(['success' => false, 'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.']], 403);
        }

        $data = FeatureClassification::orderBy('feature_code')->get(['id', 'feature_code', 'tier', 'created_at', 'updated_at']);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json(['success' => false, 'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.']], 403);
        }

        $validated = $request->validate([
            'feature_code' => ['required', 'string', 'max:100', Rule::unique('feature_classifications', 'feature_code')],
            'tier' => ['required', 'string', Rule::in(['mvp', 'addon'])],
        ]);

        $entry = FeatureClassification::create($validated);

        return response()->json(['success' => true, 'data' => $entry], 201);
    }

    public function update(Request $request, FeatureClassification $featureClassification): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json(['success' => false, 'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.']], 403);
        }

        $validated = $request->validate([
            'tier' => ['required', 'string', Rule::in(['mvp', 'addon'])],
        ]);

        $featureClassification->update($validated);

        return response()->json(['success' => true, 'data' => $featureClassification]);
    }

    public function destroy(Request $request, FeatureClassification $featureClassification): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json(['success' => false, 'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.']], 403);
        }

        $featureClassification->delete();

        return response()->json(['success' => true, 'message' => 'Deleted']);
    }

    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();
        return $user ? $user->isGlobalHcmAdmin() : false;
    }
}
