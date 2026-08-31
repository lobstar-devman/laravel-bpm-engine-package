<?php

namespace Lobstar\BpmEngine\Core;

use Illuminate\Contracts\Events\Dispatcher;
use Lobstar\BpmEngine\Bpmn\BpmnInterpreter;
use Lobstar\BpmEngine\Bpmn\BpmnProcessModel;
use Lobstar\BpmEngine\Events\TransitionRoleContext;
use Lobstar\BpmEngine\Models\Instance;

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
        protected BpmnInterpreter $bpmnInterpreter,
        protected Dispatcher $events,
    ) {}

    /**
     * Drives $instance through its current model revision via $event.
     * On success, dispatches TransitionRoleContext after the transition
     * is recorded (see ADR-005) — not before execution.
     */
    public function transition(mixed $instance, string $event): mixed
    {
        $instance = $this->resolveInstance($instance);
        $revision = $instance->modelRevision;
        $definition = $revision->modelDefinition;

        if ($definition->standard !== 'bpmn') {
            throw new \RuntimeException(
                "RevisionManager::transition() only supports the [bpmn] standard currently; got [{$definition->standard}]."
            );
        }

        $model = $this->modelRegistry->resolve($definition->key, $revision->revision_number);

        $newState = $this->bpmnInterpreter->drive($instance, $model, $event);

        $instance->current_state = $newState;
        $instance->save();

        $this->eventStore->append($instance, $event, ['to' => $newState]);

        $this->events->dispatch(new TransitionRoleContext(
            instance: $instance,
            event: $event,
            standard: $definition->standard,
            role: $model instanceof BpmnProcessModel ? $model->node($newState)->role : null,
        ));

        return $newState;
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

    private function resolveInstance(mixed $instance): Instance
    {
        return $instance instanceof Instance ? $instance : Instance::findOrFail($instance);
    }
}
