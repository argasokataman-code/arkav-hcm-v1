<?php

namespace App\Http\Controllers\Api\Calendar;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmCalendarEventController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        $userId = $request->user()->id;

        $events = CalendarEvent::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->orderBy('start_at')
            ->get()
            ->map(fn (CalendarEvent $event) => $this->formatEvent($event))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        $userId = $request->user()->id;

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'startAt' => ['required', 'date'],
            'endAt' => ['nullable', 'date', 'after_or_equal:startAt'],
            'allDay' => ['nullable', 'boolean'],
        ]);

        $event = CalendarEvent::query()->create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'title' => $validated['title'],
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'start_at' => $validated['startAt'],
            'end_at' => $validated['endAt'] ?? null,
            'all_day' => (bool) ($validated['allDay'] ?? false),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatEvent($event),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        $userId = $request->user()->id;

        $event = CalendarEvent::query()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'startAt' => ['sometimes', 'date'],
            'endAt' => ['nullable', 'date', 'after_or_equal:startAt'],
            'allDay' => ['sometimes', 'boolean'],
        ]);

        $payload = [];

        if (array_key_exists('title', $validated)) {
            $payload['title'] = $validated['title'];
        }
        if (array_key_exists('location', $validated)) {
            $payload['location'] = $validated['location'];
        }
        if (array_key_exists('description', $validated)) {
            $payload['description'] = $validated['description'];
        }
        if (array_key_exists('startAt', $validated)) {
            $payload['start_at'] = $validated['startAt'];
        }
        if (array_key_exists('endAt', $validated)) {
            $payload['end_at'] = $validated['endAt'];
        }
        if (array_key_exists('allDay', $validated)) {
            $payload['all_day'] = (bool) $validated['allDay'];
        }

        $event->update($payload);

        return response()->json([
            'success' => true,
            'data' => $this->formatEvent($event->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        $userId = $request->user()->id;

        $event = CalendarEvent::query()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $event->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    private function formatEvent(CalendarEvent $event): array
    {
        return [
            'id' => $event->id,
            'uuid' => $event->uuid,
            'title' => $event->title,
            'location' => $event->location,
            'description' => $event->description,
            'startAt' => $event->start_at?->toIso8601String(),
            'endAt' => $event->end_at?->toIso8601String(),
            'allDay' => (bool) $event->all_day,
            'createdAt' => $event->created_at?->toIso8601String(),
            'updatedAt' => $event->updated_at?->toIso8601String(),
        ];
    }
}
