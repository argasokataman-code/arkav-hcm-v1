<?php

namespace App\Http\Controllers;

use App\Models\AuthToken;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    /**
     * Generate or return an API token for the currently authenticated user
     */
    public function getToken(Request $request)
    {
        $token = $request->attributes->get('authToken');
        $user = $request->user() ?: ($token?->user);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
            ], 401);
        }

        // Raw token values are not persisted in DB, so always mint a new one.
        $rawToken = bin2hex(random_bytes(32));
        $createdToken = AuthToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $rawToken,
                'expiresAt' => $createdToken->expires_at,
            ],
        ]);
    }
}
