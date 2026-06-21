<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Models\DatabaseNotification;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Support\Exports\TabularExportResponse;
use App\Support\Hcm\NotificationEventCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class HcmNotificationController extends Controller
{
    public function templateCatalog(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Unauthenticated.'],
            ], 401);
        }

        if (! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $items = collect(NotificationEventCatalog::all())
            ->map(function (array $definition, string $eventKey): array {
                return [
                    'eventKey' => $eventKey,
                    'title' => (string) ($definition['title'] ?? $eventKey),
                    'description' => (string) ($definition['description'] ?? ''),
                    'severity' => (string) ($definition['severity'] ?? 'informational'),
                    'channels' => ['database', 'mail', 'sms', 'webhook'],
                    'digestModes' => ['instant', 'daily', 'weekly'],
                    'isEditable' => false,
                ];
            })
            ->sortBy('eventKey')
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'meta' => [
                    'total' => count($items),
                    'mode' => 'stub',
                ],
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Unauthenticated.'],
            ], 401);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'isRead' => ['nullable', 'boolean'],
            'eventKey' => ['nullable', 'string', 'max:191'],
            'channel' => ['nullable', 'string', 'in:database,mail,sms,webhook'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['perPage'] ?? 20);
        $activeCompanyUuid = (string) ($request->attributes->get('activeCompanyUuid') ?? '');
        $eventKeyFilter = isset($validated['eventKey']) ? (string) $validated['eventKey'] : null;
        $channelFilter = isset($validated['channel']) ? (string) $validated['channel'] : null;
        $isReadFilter = array_key_exists('isRead', $validated)
            ? (bool) $validated['isRead']
            : null;

        $collection = $user->notifications()->get()
            ->filter(function (DatabaseNotification $notification) use ($activeCompanyUuid, $user, $eventKeyFilter, $channelFilter, $isReadFilter): bool {
                $data = (array) ($notification->data ?? []);
                $eventKey = (string) ($data['eventKey'] ?? $data['event'] ?? '');
                $channel = (string) ($data['channel'] ?? 'database');
                $companyUuid = (string) ($data['companyUuid'] ?? '');
                $isRead = $notification->read_at !== null;

                if ($isReadFilter !== null && $isReadFilter !== $isRead) {
                    return false;
                }

                if ($eventKeyFilter !== null && $eventKey !== $eventKeyFilter) {
                    return false;
                }

                if ($channelFilter !== null && $channel !== $channelFilter) {
                    return false;
                }

                if (! $user->isGlobalHcmAdmin() && $activeCompanyUuid !== '' && $companyUuid !== '' && $companyUuid !== $activeCompanyUuid) {
                    return false;
                }

                return true;
            })
            ->sortByDesc(static fn (DatabaseNotification $notification) => $notification->created_at?->getTimestamp() ?? 0)
            ->values();

        $total = $collection->count();
        $unreadCount = $collection->filter(static fn (DatabaseNotification $notification): bool => $notification->read_at === null)->count();

        $items = $collection->forPage($page, $perPage)->map(function (DatabaseNotification $notification): array {
            $data = (array) ($notification->data ?? []);

            return [
                'uuid' => (string) $notification->id,
                'eventKey' => (string) ($data['eventKey'] ?? $data['event'] ?? ''),
                'title' => (string) ($data['title'] ?? ''),
                'body' => (string) ($data['message'] ?? ''),
                'severity' => (string) ($data['severity'] ?? 'informational'),
                'channel' => (string) ($data['channel'] ?? 'database'),
                'isRead' => $notification->read_at !== null,
                'readAt' => $notification->read_at?->toIso8601String(),
                'createdAt' => $notification->created_at?->toIso8601String(),
                'data' => $data,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'meta' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'unreadCount' => $unreadCount,
                ],
            ],
        ]);
    }

    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Unauthenticated.'],
            ], 401);
        }

        $notification = $user->notifications()->find($notificationId);
        if (! $notification) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Notification not found.'],
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'data' => ['uuid' => (string) $notification->id],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Unauthenticated.'],
            ], 401);
        }

        $updated = DB::table('notifications')
            ->where(function ($query) use ($user): void {
                $query->where('user_uuid', $user->uuid)
                    ->orWhere(function ($legacy) use ($user): void {
                        $legacy->whereNull('user_uuid')
                            ->where('notifiable_type', User::class)
                            ->where('notifiable_id', (string) $user->id);
                    });
            })
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => ['updated' => (int) $updated],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Unauthenticated.'],
            ], 401);
        }

        $count = $user->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'data' => ['unreadCount' => $count],
        ]);
    }

    public function deliverySummary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $activeCompanyUuid = (string) ($request->attributes->get('activeCompanyUuid') ?? '');
        if ($activeCompanyUuid === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active tenant context is required.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'channel' => ['nullable', 'string'],
        ]);

        $hours = (int) ($validated['hours'] ?? 24);
        $channel = isset($validated['channel']) ? (string) $validated['channel'] : null;
        $windowStart = now()->subHours($hours);

        $baseQuery = NotificationDelivery::where('created_at', '>=', $windowStart)
            ->where('company_uuid', $activeCompanyUuid);

        if ($channel !== null) {
            $baseQuery->where('channel', $channel);
        }

        $totals = [
            'all' => $baseQuery->clone()->count(),
            'sent' => $baseQuery->clone()->where('status', 'sent')->count(),
            'failed' => $baseQuery->clone()->where('status', 'failed')->count(),
            'dropped' => $baseQuery->clone()->where('status', 'dropped')->count(),
        ];

        $breakdown = [
            'byChannel' => $baseQuery->clone()
                ->select('channel', DB::raw('COUNT(*) as count'))
                ->groupBy('channel')
                ->get()
                ->map(fn ($row) => ['channel' => $row->channel, 'count' => (int) $row->count])
                ->values()
                ->toArray(),
        ];

        $topFailedEvents = $baseQuery->clone()
            ->where('status', 'failed')
            ->select('event_key', DB::raw('COUNT(*) as count'))
            ->groupBy('event_key')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['eventKey' => $row->event_key, 'count' => (int) $row->count])
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'totals' => $totals,
                'breakdown' => $breakdown,
                'topFailedEvents' => $topFailedEvents,
                'windowHours' => $hours,
            ],
        ]);
    }

    public function deliveryDetails(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $activeCompanyUuid = (string) ($request->attributes->get('activeCompanyUuid') ?? '');
        if ($activeCompanyUuid === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active tenant context is required.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'in:sent,failed,dropped,pending'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'channel' => ['nullable', 'string', 'in:database,mail,sms,webhook'],
            'eventKey' => ['nullable', 'string', 'max:191'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['perPage'] ?? 10);
        $status = isset($validated['status']) ? (string) $validated['status'] : null;
        $hours = (int) ($validated['hours'] ?? 24);
        $channel = isset($validated['channel']) ? (string) $validated['channel'] : null;
        $eventKey = isset($validated['eventKey']) ? (string) $validated['eventKey'] : null;
        $windowStart = now()->subHours($hours);

        $baseQuery = NotificationDelivery::query()
            ->where('created_at', '>=', $windowStart)
            ->where('company_uuid', $activeCompanyUuid)
            ->orderByDesc('created_at');

        if ($status !== null) {
            $baseQuery->where('status', $status);
        }

        if ($channel !== null) {
            $baseQuery->where('channel', $channel);
        }

        if ($eventKey !== null) {
            $baseQuery->where('event_key', $eventKey);
        }

        $total = $baseQuery->clone()->count();
        $items = $baseQuery->forPage($page, $perPage)
            ->get()
            ->map(function (NotificationDelivery $delivery): array {
                return [
                    'id' => (int) $delivery->id,
                    'eventKey' => (string) $delivery->event_key,
                    'channel' => (string) $delivery->channel,
                    'status' => (string) $delivery->status,
                    'recipient' => (string) $delivery->recipient,
                    'attemptCount' => (int) $delivery->attempt_count,
                    'lastError' => (string) ($delivery->last_error ?? ''),
                    'createdAt' => $delivery->created_at?->toIso8601String(),
                    'metadata' => (array) ($delivery->metadata ?? []),
                ];
            })
            ->values()
            ->toArray();

        $perPageInt = (int) $perPage;
        $totalPages = (int) ceil($total / $perPageInt);
        $hasMore = $page < $totalPages;

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'meta' => [
                    'page' => (int) $page,
                    'perPage' => $perPageInt,
                    'total' => (int) $total,
                    'hasMore' => $hasMore,
                ],
            ],
        ]);
    }

    public function exportDeliveries(Request $request): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Unauthenticated.'],
            ], 401);
        }

        if (! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $activeCompanyUuid = (string) ($request->attributes->get('activeCompanyUuid') ?? '');
        if ($activeCompanyUuid === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active tenant context is required.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:sent,failed,dropped'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'channel' => ['nullable', 'string', 'in:database,mail,sms,webhook'],
            'eventKey' => ['nullable', 'string', 'max:191'],
            'format' => ['nullable', 'string', 'in:xlsx,csv'],
        ]);

        $status = isset($validated['status']) ? (string) $validated['status'] : null;
        $hours = (int) ($validated['hours'] ?? 24);
        $channel = isset($validated['channel']) ? (string) $validated['channel'] : null;
        $eventKey = isset($validated['eventKey']) ? (string) $validated['eventKey'] : null;
        $windowStart = now()->subHours($hours);

        $baseQuery = NotificationDelivery::query()
            ->where('created_at', '>=', $windowStart)
            ->where('company_uuid', $activeCompanyUuid)
            ->orderByDesc('created_at');

        if ($status !== null) {
            $baseQuery->where('status', $status);
        }

        if ($channel !== null) {
            $baseQuery->where('channel', $channel);
        }

        if ($eventKey !== null) {
            $baseQuery->where('event_key', $eventKey);
        }

        $items = $baseQuery->get();

        $format = strtolower((string) ($validated['format'] ?? 'xlsx'));
        $headers = ['Timestamp', 'Event Key', 'Channel', 'Status', 'Recipient', 'Attempts', 'Last Error'];
        $rows = $items->map(static function (NotificationDelivery $delivery): array {
            return [
                $delivery->created_at?->format('Y-m-d H:i:s') ?? '',
                (string) $delivery->event_key,
                (string) $delivery->channel,
                (string) $delivery->status,
                (string) $delivery->recipient,
                (int) $delivery->attempt_count,
                (string) ($delivery->last_error ?? ''),
            ];
        })->values()->all();

        return TabularExportResponse::download(
            headers: $headers,
            rows: $rows,
            filenameBase: 'notification-deliveries-'.now()->format('YmdHis'),
            format: $format,
            sheetTitle: 'Delivery Log'
        );
    }

    public function retryDelivery(Request $request, int $deliveryId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Unauthenticated.'],
            ], 401);
        }

        if (! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $activeCompanyUuid = (string) ($request->attributes->get('activeCompanyUuid') ?? '');
        if ($activeCompanyUuid === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active tenant context is required.',
                ],
            ], 422);
        }

        $delivery = NotificationDelivery::query()
            ->where('id', $deliveryId)
            ->where('company_uuid', $activeCompanyUuid)
            ->first();
        if (! $delivery) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Delivery record not found.'],
            ], 404);
        }

        $metadata = (array) ($delivery->metadata ?? []);
        if (! isset($metadata['retry_log'])) {
            $metadata['retry_log'] = [];
        }
        $metadata['retry_log'][] = [
            'actor_uuid' => (string) $user->uuid,
            'actor_email' => (string) $user->email,
            'retried_at' => now()->toIso8601String(),
            'previous_status' => (string) $delivery->status,
        ];
        $metadata['last_manual_retry'] = now()->toIso8601String();

        $delivery->update([
            'status' => 'pending',
            'attempt_count' => (int) ($delivery->attempt_count ?? 0) + 1,
            'metadata' => $metadata,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $delivery->id,
                'status' => 'pending',
                'attemptCount' => (int) $delivery->attempt_count,
                'message' => 'Delivery marked for retry',
            ],
        ]);
    }
}
