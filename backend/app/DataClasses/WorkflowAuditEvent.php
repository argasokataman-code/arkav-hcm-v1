<?php

namespace App\DataClasses;

/**
 * Immutable record of a single workflow stage transition event.
 *
 * An array of these is stored in workflow_history JSON column so every
 * stage change is fully auditable (who, when, from → to, optional note).
 * Used by Slice B audit trail feature.
 */
final class WorkflowAuditEvent
{
    public function __construct(
        public readonly string  $previousStage,
        public readonly string  $newStage,
        public readonly string  $action,       // 'submit_review' | 'approve' | 'finalize' | 'cancel' | 'revert'
        public readonly int     $actorId,
        public readonly string  $actorName,
        public readonly string  $actorRole,    // 'admin' | 'hr_manager' | 'employee' | 'system'
        public readonly string  $timestamp,    // ISO-8601
        public readonly ?string $note = null,
    ) {}

    /**
     * Serialize to plain array for JSON storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'previous_stage' => $this->previousStage,
            'new_stage'      => $this->newStage,
            'action'         => $this->action,
            'actor_id'       => $this->actorId,
            'actor_name'     => $this->actorName,
            'actor_role'     => $this->actorRole,
            'timestamp'      => $this->timestamp,
            'note'           => $this->note,
        ];
    }

    /**
     * Build from a request + actor context.
     *
     * @param \App\Models\User $actor
     */
    public static function make(
        string $previousStage,
        string $newStage,
        string $action,
        object $actor,
        ?string $note = null,
    ): self {
        return new self(
            previousStage: $previousStage,
            newStage:      $newStage,
            action:        $action,
            actorId:       $actor->id,
            actorName:     $actor->name ?? ($actor->email ?? 'unknown'),
            actorRole:     $actor->roles?->first()?->name ?? 'employee',
            timestamp:     now()->toIso8601String(),
            note:          $note,
        );
    }
}
