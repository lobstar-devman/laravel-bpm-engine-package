<?php

namespace Lobstar\BpmEngine\Core;

use Illuminate\Contracts\Events\Dispatcher;
use Lobstar\BpmEngine\Bpmn\BpmnInterpreter;
use Lobstar\BpmEngine\Bpmn\BpmnProcessModel;
use Lobstar\BpmEngine\Cmmn\CmmnCaseModel;
use Lobstar\BpmEngine\Cmmn\CmmnInterpreter;
use Lobstar\BpmEngine\Events\TransitionRoleContext;
use Lobstar\BpmEngine\Models\Instance;
use Lobstar\BpmEngine\Models\ModelRevision;
use Lobstar\BpmEngine\Models\TransitionEvent;

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
    /** The event_type recorded for a rollback, per Section 6 ("Roll back to a prior model revision"). */
    private const ROLLBACK_EVENT_TYPE = 'RolledBackEvent';

    public function __construct(
        protected ModelRegistry $modelRegistry,
        protected EventStore $eventStore,
        protected BpmnInterpreter $bpmnInterpreter,
        protected CmmnInterpreter $cmmnInterpreter,
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

        $model = $this->modelRegistry->resolve($definition->key, $revision->revision_number);

        $newState = $this->drive($definition->standard, $instance, $model, $event);

        $instance->current_state = $newState;
        $instance->save();

        $this->eventStore->append($instance, $event, ['to' => $newState]);

        $this->events->dispatch(new TransitionRoleContext(
            instance: $instance,
            event: $event,
            standard: $definition->standard,
            role: $this->roleFor($model, $newState),
        ));

        return $newState;
    }

    /**
     * Rolls $instance back to $targetRevision: resolves the target
     * revision's model, recomputes state by replaying $instance's
     * recorded event history against it, and appends a RolledBackEvent.
     * On success, dispatches TransitionRoleContext after the rollback
     * is recorded (see ADR-005) — not before execution.
     */
    public function rollback(mixed $instance, int $targetRevision): mixed
    {
        $instance = $this->resolveInstance($instance);
        $currentRevision = $instance->modelRevision;
        $definition = $currentRevision->modelDefinition;

        $targetModel = $this->modelRegistry->resolve($definition->key, $targetRevision);

        $targetRevisionModel = ModelRevision::where('model_definition_id', $definition->id)
            ->where('revision_number', $targetRevision)
            ->firstOrFail();

        $history = $this->eventStore->history($instance);

        $restoredState = $this->recompute($definition->standard, $targetModel, $history);

        $instance->model_revision_id = $targetRevisionModel->id;
        $instance->current_state = $restoredState;
        $instance->save();

        $this->eventStore->append($instance, self::ROLLBACK_EVENT_TYPE, [
            'from_revision' => $currentRevision->revision_number,
            'to_revision' => $targetRevision,
            'state' => $restoredState,
        ]);

        $this->events->dispatch(new TransitionRoleContext(
            instance: $instance,
            event: self::ROLLBACK_EVENT_TYPE,
            standard: $definition->standard,
            role: $this->roleFor($targetModel, $restoredState),
        ));

        return $restoredState;
    }

    /**
     * Replays $history's recorded transitions (in order) against $model
     * from its start node, skipping prior RolledBackEvent entries — they
     * aren't standard-defined triggering events, just markers of an
     * earlier rollback.
     *
     * @param  list<TransitionEvent>  $history
     */
    private function recompute(string $standard, mixed $model, array $history): string
    {
        $cursor = new \stdClass;
        $cursor->current_state = null;

        foreach ($history as $transitionEvent) {
            if ($transitionEvent->event_type === self::ROLLBACK_EVENT_TYPE) {
                continue;
            }

            $cursor->current_state = $this->drive($standard, $cursor, $model, $transitionEvent->event_type);
        }

        return $cursor->current_state ?? $this->startNodeIdOf($model);
    }

    /** Dispatches to the standard-appropriate Interpreter. */
    private function drive(string $standard, mixed $instance, mixed $model, string $event): string
    {
        return match ($standard) {
            'bpmn' => $this->bpmnInterpreter->drive($instance, $model, $event),
            'cmmn' => $this->cmmnInterpreter->drive($instance, $model, $event),
            default => throw new \RuntimeException(
                "RevisionManager only supports the [bpmn]/[cmmn] standards currently; got [{$standard}]."
            ),
        };
    }

    private function roleFor(mixed $model, string $nodeId): ?string
    {
        return match (true) {
            $model instanceof BpmnProcessModel => $model->node($nodeId)->role,
            $model instanceof CmmnCaseModel => $model->node($nodeId)->role,
            default => null,
        };
    }

    private function startNodeIdOf(mixed $model): string
    {
        return match (true) {
            $model instanceof BpmnProcessModel => $model->startNodeId,
            $model instanceof CmmnCaseModel => $model->startNodeId,
            default => throw new \InvalidArgumentException('RevisionManager::recompute() requires a parsed BpmnProcessModel or CmmnCaseModel.'),
        };
    }

    private function resolveInstance(mixed $instance): Instance
    {
        return $instance instanceof Instance ? $instance : Instance::findOrFail($instance);
    }
}
