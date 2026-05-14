<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HcmNotificationPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Unauthenticated.'],
            ], 401);
        }

        $items = NotificationPreference::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_uuid', $user->uuid)
                    ->orWhere(function ($legacy) use ($user): void {
                        $legacy->whereNull('user_uuid')->where('user_id', $user->id);
                    });
            })
            ->orderBy('event_key')
            ->orderBy('channel')
            ->get()
            ->map(static fn (NotificationPreference $preference): array => [
                'eventKey' => (string) $preference->event_key,
                'channel' => (string) $preference->channel,
                'enabled' => (bool) $preference->enabled,
                'digestMode' => (string) $preference->digest_mode,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Unauthenticated.'],
            ], 401);
        }

        $validated = $request->validate([
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.eventKey' => ['required', 'string', 'max:191'],
            'preferences.*.channel' => ['required', 'string', 'in:database,mail,sms,webhook'],
            'preferences.*.enabled' => ['required', 'boolean'],
            'preferences.*.digestMode' => ['nullable', 'string', 'in:instant,daily,weekly'],
        ]);

        DB::transaction(function () use ($validated, $user): void {
            foreach ((array) $validated['preferences'] as $item) {
                NotificationPreference::query()->updateOrCreate(
                    [
                        'user_uuid' => $user->uuid,
                        'event_key' => (string) $item['eventKey'],
                        'channel' => (string) $item['channel'],
                    ],
                    [
                        'user_id' => $user->id,
                        'enabled' => (bool) $item['enabled'],
                        'digest_mode' => (string) ($item['digestMode'] ?? 'instant'),
                    ],
                );
            }
        });

        return $this->index($request);
    }
}
