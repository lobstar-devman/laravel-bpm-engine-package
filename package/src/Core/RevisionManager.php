<?php

namespace Lobstar\BpmEngine\Core;

/**
 * Manages transitions between model revisions for active entities,
 * including rollback. See docs/arc42/06-runtime-view.md.
 *
 * Per ADR-005, once a transition is durably recorded via
 * EventStore::append(), dispatches a
 * Lobstar\BpmEngine\Events\TransitionRoleContext event carrying the
 * lane/role (BPMN) or case role (CMMN) responsible for the activity.
 * This is purely observational and non-blocking — it does not gate the
 * transition, which has already happened by the time it fires.
 */
class RevisionManager
{
    public function __construct(
        protected ModelRegistry $modelRegistry,
        protected EventStore $eventStore,
    ) {
    }

    /**
     * Drives $instance through its current model revision via $event.
     * On success, dispatches TransitionRoleContext after the transition
     * is recorded (see ADR-005) — not before execution.
     */
    public function transition(mixed $instance, string $event): mixed
    {
        throw new \RuntimeException('Not implemented yet.');
    }

    /**
     * Rolls $instance back to $targetRevision. On success, dispatches
     * TransitionRoleContext after the rollback is recorded (see
     * ADR-005) — not before execution.
     */
    public function rollback(mixed $instance, int $targetRevision): mixed
    {
        throw new \RuntimeException('Not implemented yet.');
    }
}
