<?php

namespace App\Http\Controllers;

use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    /**
     * Generate or return an API token for the currently authenticated user
     */
    public function getToken(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
            ], 401);
        }

        // Check if user already has a valid token
        $existingToken = AuthToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($existingToken) {
            $rawToken = $existingToken->token;
        } else {
            // Create a new token
            $token = bin2hex(random_bytes(32));
            $existingToken = AuthToken::create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $token),
                'name' => 'Web Dashboard Token',
                'expires_at' => now()->addDays(30),
            ]);
            $rawToken = $token;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $rawToken,
                'expiresAt' => $existingToken->expires_at,
            ],
        ]);
    }
}
