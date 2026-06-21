<?php

namespace App\Services\Hcm;

use App\DataClasses\WorkflowAuditEvent;
use App\Models\HcmTermination;

/**
 * TerminationWorkflowValidator
 *
 * Validates stage transitions and records audit trail entries.
 *
 * Slice B responsibilities:
 *  - Strict allowed-transition matrix (no skipping stages)
 *  - Append a WorkflowAuditEvent to the workflow_history JSON array
 *  - Optimistic lock: check workflow_version matches the client's version
 *    and return 409 Conflict if they diverge (Anomaly #2 mitigation)
 *
 * NOTE: The transition matrix here is STRICTER than the legacy
 * `isWorkflowTransitionAllowed()` in the controller.  The controller's
 * method remains for backward-compatible GET/preview logic; all mutating
 * update paths MUST use this service going forward.
 */
final class TerminationWorkflowValidator
{
    /**
     * Strict transition matrix — no stage skipping allowed.
     *
     * Keys = current stage, values = allowed next stages.
     */
    private const TRANSITIONS = [
        'draft_review' => ['legal_review', 'cancelled'],
        'legal_review' => ['approved_internal', 'cancelled'],
        'approved_internal' => ['finalized_execution', 'cancelled'],
        'finalized_execution' => [],  // terminal — no further transitions
        'cancelled' => [],  // terminal
    ];

    /**
     * Validate that a stage transition is allowed.
     *
     * @return string|null null = ok; non-null = human-readable error reason
     */
    public function validateTransition(?string $currentStage, string $nextStage): ?string
    {
        $current = $currentStage ?: 'draft_review';

        if ($current === $nextStage) {
            return null; // No-op — same stage, no action needed
        }

        $allowed = self::TRANSITIONS[$current] ?? [];
        if (in_array($nextStage, $allowed, true)) {
            return null;
        }

        $isTerminal = empty(self::TRANSITIONS[$current] ?? null) && isset(self::TRANSITIONS[$current]);
        if ($isTerminal || in_array($current, ['finalized_execution', 'cancelled'], true)) {
            return "Cannot transition from terminal stage '{$current}'.";
        }

        $allowedList = implode(', ', $allowed);

        return "Invalid workflow transition '{$current}' → '{$nextStage}'. "
            ."Allowed from '{$current}': [{$allowedList}].";
    }

    /**
     * Validate the optimistic lock version from the client request.
     *
     * Returns a conflict reason string if versions mismatch, null if ok.
     * Callers should return HTTP 409 on conflict (Anomaly #2).
     */
    public function validateVersion(HcmTermination $termination, ?int $clientVersion): ?string
    {
        // If client did not send a version we skip the check for backward compatibility.
        if ($clientVersion === null) {
            return null;
        }

        $serverVersion = (int) ($termination->workflow_version ?? 0);
        if ($clientVersion !== $serverVersion) {
            return "Workflow version conflict: client has version {$clientVersion}, "
                ."server has version {$serverVersion}. Reload and retry.";
        }

        return null;
    }

    /**
     * Build the updated workflow_history array by appending a new audit event.
     *
     * @return list<array<string, mixed>>
     */
    public function appendHistory(HcmTermination $termination, WorkflowAuditEvent $event): array
    {
        $history = is_array($termination->workflow_history) ? $termination->workflow_history : [];
        $history[] = $event->toArray();

        return $history;
    }

    /**
     * Map a workflow stage to the action label used in audit events.
     */
    public function stageToAction(string $nextStage): string
    {
        return match ($nextStage) {
            'legal_review' => 'submit_review',
            'approved_internal' => 'approve',
            'finalized_execution' => 'finalize',
            'cancelled' => 'cancel',
            default => 'update',
        };
    }

    /**
     * Check if a stage is terminal (no further transitions allowed).
     */
    public function isTerminal(string $stage): bool
    {
        return empty(self::TRANSITIONS[$stage] ?? null)
            && array_key_exists($stage, self::TRANSITIONS);
    }
}
