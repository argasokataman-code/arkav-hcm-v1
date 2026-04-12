<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait EnsuresHcmAdmin
{
    protected function ensureHcmAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if ($user && $user->isHcmAdmin()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'AUTH_FORBIDDEN',
                'message' => 'Forbidden.',
            ],
        ], 403);
    }
}
