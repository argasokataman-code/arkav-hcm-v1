<?php

use App\Mail\AdminComposeMailable;
use App\Models\NotificationDelivery;
use App\Services\NotificationDeliveryRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/voice-call', function () {
    return view('applications.voice-call');
})->name('voice-call');

Route::get('/video-call', function () {
    return view('applications.video-call');
})->name('video-call');

Route::get('/outgoing-call', function () {
    return view('applications.outgoing-call');
})->name('outgoing-call');

Route::get('/incoming-call', function () {
    return view('applications.incoming-call');
})->name('incoming-call');

Route::get('/call-history', function () {
    return view('applications.call-history');
})->name('call-history');

Route::get('/group-video-call', function () {
    return view(view: 'group-video-call');
})->name('group-video-call');

Route::match(['get', 'post'], '/email', function (Request $request) {
    $redirectParameters = [];
    $label = trim((string) $request->input('Label', $request->query('Label', '')));
    if ($label !== '') {
        $redirectParameters['Label'] = $label;
    }

    if ($request->isMethod('post')) {
        $validated = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        try {
            Mail::to($validated['to'])->send(new AdminComposeMailable(
                subjectLine: $validated['subject'],
                messageBody: $validated['message'],
                senderName: (string) ($request->user()?->name ?? config('app.name', 'Arkav')),
            ));

            app(NotificationDeliveryRecorder::class)->recordSent('email.compose.sent', 'mail', [
                'recipient' => $validated['to'],
                'metadata' => [
                    'subject' => $validated['subject'],
                    'messagePreview' => mb_substr($validated['message'], 0, 160),
                    'senderUserId' => (int) ($request->user()?->id ?? 0),
                    'senderEmail' => (string) ($request->user()?->email ?? ''),
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('email', $redirectParameters)
                ->withInput()
                ->withErrors([
                    'compose' => 'Email gagal dikirim. Cek konfigurasi email aktif lalu coba lagi.',
                ]);
        }

        return redirect()
            ->route('email', $redirectParameters)
            ->with('status', 'Email berhasil dikirim ke '.$validated['to'].'.');
    }

    $currentUserId = (int) ($request->user()?->id ?? 0);
    $currentUserEmail = strtolower(trim((string) ($request->user()?->email ?? '')));

    $deliveries = NotificationDelivery::query()
        ->whereIn('event_key', ['email.compose.sent', 'email.inbound.received'])
        ->where('channel', 'mail')
        ->latest('created_at')
        ->limit(200)
        ->get();

    $sentItems = $deliveries
        ->filter(function (NotificationDelivery $delivery) use ($currentUserId): bool {
            if ($delivery->event_key !== 'email.compose.sent') {
                return false;
            }

            return (int) ($delivery->metadata['senderUserId'] ?? 0) === $currentUserId;
        })
        ->values()
        ->map(function (NotificationDelivery $delivery): array {
            return [
                'to' => (string) ($delivery->recipient ?? ''),
                'subject' => (string) ($delivery->metadata['subject'] ?? '(No subject)'),
                'preview' => (string) ($delivery->metadata['messagePreview'] ?? ''),
                'sentAt' => optional($delivery->sent_at)->diffForHumans() ?: '-',
                'sentAtIso' => optional($delivery->sent_at)->toIso8601String(),
            ];
        })
        ->all();

    $inboxItems = $deliveries
        ->filter(function (NotificationDelivery $delivery) use ($currentUserEmail): bool {
            if ($delivery->event_key !== 'email.inbound.received') {
                return false;
            }

            if ($currentUserEmail === '') {
                return true;
            }

            $recipient = strtolower(trim((string) ($delivery->recipient ?? '')));
            if ($recipient === '') {
                return true;
            }

            return str_contains($recipient, $currentUserEmail);
        })
        ->values()
        ->map(function (NotificationDelivery $delivery): array {
            return [
                'from' => (string) ($delivery->metadata['from'] ?? '-'),
                'subject' => (string) ($delivery->metadata['subject'] ?? '(No subject)'),
                'preview' => (string) ($delivery->metadata['messagePreview'] ?? ''),
                'receivedAt' => optional($delivery->sent_at)->diffForHumans() ?: '-',
                'receivedAtIso' => optional($delivery->sent_at)->toIso8601String(),
            ];
        })
        ->all();

    return view('email', [
        'inboxItems' => $inboxItems,
        'sentItems' => $sentItems,
        'inboxCount' => count($inboxItems),
        'sentCount' => count($sentItems),
        'totalCount' => count($sentItems) + count($inboxItems),
    ]);
})->name('email');

Route::get('/email-reply', function () {
    return view(view: 'email-reply');
})->name('email-reply');

Route::get('/notes', function () {
    return view('applications.notes');
})->name('notes');

Route::get('/social-feed', function () {
    return view('applications.social-feed');
})->name('social-feed');

Route::get('/calendar', function () {
    return view('applications.calendar');
})->name('calendar');
