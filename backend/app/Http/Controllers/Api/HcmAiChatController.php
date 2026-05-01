<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\AiChatLog;
use App\Services\Ai\AiIntentClassifier;
use App\Services\Ai\AiIntentGate;
use App\Services\Ai\AiIntentResolver;
use App\Services\Ai\AiLlmService;
use App\Support\ArcavAccessTokenResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HcmAiChatController extends Controller
{
    use ChecksPermissions;

    private const SYSTEM_PROMPT = <<<'PROMPT'
        Kamu adalah asisten HRMS Arkav. Tugasmu adalah membantu pertanyaan seputar HR, absensi, cuti, gaji, tiket, dan pengelolaan karyawan.

        Aturan menjawab:
        1. Jika DATA CONTEXT tersedia, prioritaskan menjawab berdasarkan data tersebut — faktual, singkat, gunakan bullet point untuk daftar.
        2. Jika pertanyaan bersifat prosedural/panduan (cara mengajukan cuti, cara absen, cara melihat payslip, dll), jawab berdasarkan pengetahuan umum HRMS Arkav — singkat dan jelas.
        3. Jika pertanyaan sama sekali di luar domain HRMS (cuaca, berita umum, dll), katakan: "Maaf, saya hanya dapat membantu pertanyaan seputar HRMS Arkav."
        4. Jangan sebutkan nama endpoint, tabel database, atau detail teknis internal sistem.
        5. Jawab dalam bahasa yang sama dengan pertanyaan user (Indonesia atau Inggris).
        PROMPT;

    public function __construct(
        private readonly AiIntentClassifier $classifier,
        private readonly AiIntentGate $gate,
        private readonly AiIntentResolver $resolver,
        private readonly AiLlmService $llm,
    ) {}

    /**
     * POST /v1/hcm/ai/chat
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message'    => ['required', 'string', 'min:2', 'max:500'],
            'session_id' => ['nullable', 'string', 'uuid'],
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'error' => ['code' => 'AUTH_UNAUTHENTICATED', 'message' => 'Unauthenticated.']], 401);
        }

        $message   = trim((string) $validated['message']);
        $sessionId = (string) ($validated['session_id'] ?? Str::uuid());
        $companyId = $this->activeCompanyId($request);

        // 1. Classify intent (no LLM call yet)
        $intent = $this->classifier->classify($message);

        // 2. RBAC gate — check BEFORE fetching any data
        if (! $this->gate->allows($user, $intent, $companyId)) {
            // For unknown intent, admins get a broad-context fallback instead of a hard denial
            if ($intent === 'unknown' || $intent === '') {
                $fallback = $this->gate->fallbackIntentFor($user, $companyId);
                if ($fallback !== null) {
                    $intent = $fallback;
                }
            }

            // Re-check after potential intent swap; deny if still not allowed
            if (! $this->gate->allows($user, $intent, $companyId)) {
                $denyReason = $this->gate->isKnownIntent($intent) ? 'permission_denied' : 'intent_unknown';
                $this->writeLog($user->uuid, $companyId, $sessionId, $intent, false, $denyReason, []);

                return response()->json([
                    'success' => true,
                    'data'    => [
                        'reply'      => $this->denyMessage($denyReason),
                        'intent'     => $intent,
                        'allowed'    => false,
                        'sources'    => [],
                        'session_id' => $sessionId,
                    ],
                ]);
            }
        }

        // 3. Extract bearer token from current request to pass to internal API calls
        // Prefer Authorization header, fallback to cookie (super admin auth via cookie)
        $bearerToken = (string) $request->bearerToken();
        if ($bearerToken === '') {
            $bearerToken = (string) ArcavAccessTokenResolver::rawTokenFromRequest($request);
        }

        // 4. Resolve data from internal API
        $resolved = $this->resolver->resolve($intent, $user, $companyId, $bearerToken);

        if ($resolved === null) {
            $this->writeLog($user->uuid, $companyId, $sessionId, $intent, true, 'data_not_found', []);

            return response()->json([
                'success' => true,
                'data'    => [
                    'reply'      => 'Maaf, data tidak bisa diambil saat ini. Silakan coba beberapa saat lagi atau buka halaman terkait langsung.',
                    'intent'     => $intent,
                    'allowed'    => true,
                    'sources'    => [],
                    'session_id' => $sessionId,
                ],
            ]);
        }

        // 5. Call LLM with structured context (data is already safe at this point)
        try {
            $messages = $this->llm->buildMessages(self::SYSTEM_PROMPT, $resolved['data'], $message);
            $reply    = $this->llm->chat($messages);
        } catch (\RuntimeException $e) {
            $this->writeLog($user->uuid, $companyId, $sessionId, $intent, true, 'llm_error', [$resolved['source']]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'reply'      => 'Maaf, asisten sedang tidak tersedia. Silakan coba beberapa saat lagi.',
                    'intent'     => $intent,
                    'allowed'    => true,
                    'sources'    => [$resolved['source']],
                    'session_id' => $sessionId,
                ],
            ]);
        }

        // 6. Audit log
        $this->writeLog($user->uuid, $companyId, $sessionId, $intent, true, null, [$resolved['source']]);

        return response()->json([
            'success' => true,
            'data'    => [
                'reply'      => $reply,
                'intent'     => $intent,
                'allowed'    => true,
                'sources'    => [$resolved['source']],
                'session_id' => $sessionId,
            ],
        ]);
    }

    /**
     * GET /v1/hcm/ai/intents
     * Returns list of intents available for the current user.
     */
    public function intents(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'error' => ['code' => 'AUTH_UNAUTHENTICATED', 'message' => 'Unauthenticated.']], 401);
        }

        $companyId = $this->activeCompanyId($request);
        $allowed   = $this->gate->allowedIntentsFor($user, $companyId);

        return response()->json([
            'success' => true,
            'data'    => ['intents' => $allowed],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function denyMessage(string $reason): string
    {
        return match ($reason) {
            'intent_unknown'    => 'Saya hanya dapat membantu pertanyaan seputar HRMS Arkav. Coba tanyakan hal lain seperti cuti, absensi, atau payslip kamu.',
            'permission_denied' => 'Kamu tidak memiliki akses untuk informasi ini.',
            default             => 'Permintaan ini tidak dapat diproses.',
        };
    }

    /**
     * @param  array<int, array<string, string>>  $sources
     */
    private function writeLog(string $userUuid, ?int $companyId, string $sessionId, string $intent, bool $allowed, ?string $denyReason, array $sources): void
    {
        try {
            AiChatLog::create([
                'user_uuid'        => $userUuid,
                'company_id'       => $companyId,
                'session_id'       => $sessionId,
                'intent'           => $intent,
                'allowed'          => $allowed,
                'deny_reason'      => $denyReason,
                'source_endpoints' => $sources,
            ]);
        } catch (\Throwable) {
            // best-effort; log failure must never break user response
        }
    }
}
