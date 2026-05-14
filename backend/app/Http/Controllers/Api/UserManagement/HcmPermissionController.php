<?php

namespace App\Http\Controllers\Api\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\HcmPermission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HcmPermissionController extends Controller
{
    /**
     * Get all permissions (global)
     */
    public function index(Request $request): JsonResponse
    {
        $permissions = HcmPermission::where('is_active', true)
            ->orderBy('module')
            ->orderBy('resource')
            ->orderBy('action')
            ->get()
            ->groupBy(['module', 'resource']);

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }
}