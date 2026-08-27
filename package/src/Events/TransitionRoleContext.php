<?php

namespace Lobstar\BpmEngine\Events;

/**
 * Dispatched by RevisionManager immediately after a transition is
 * durably recorded via EventStore::append(), carrying the organizational
 * role/lane (BPMN) or case role (CMMN) responsible for the activity that
 * was just transitioned. See ADR-005.
 *
 * Purely observational: the engine does not inspect listener results,
 * and because this only fires once the transition is already recorded,
 * a listener cannot abort or gate it, by construction. Real access
 * control is the host application's responsibility, exercised before
 * calling RevisionManager::transition()/rollback().
 */
class TransitionRoleContext
{
    public function __construct(
        public readonly mixed $instance,
        public readonly string $event,
        public readonly string $standard,
        public readonly ?string $role,
    ) {
    }
}
