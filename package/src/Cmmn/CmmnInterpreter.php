<?php

namespace Lobstar\BpmEngine\Cmmn;

/** Drives entities through a parsed CMMN case model. */
class CmmnInterpreter
{
    /**
     * Advances $instance (anything exposing a `current_state` property,
     * null/empty meaning "not yet started") one step through $model along
     * the outgoing entry criterion matching $event, and returns the id
     * of the resulting plan item. Mirrors BpmnInterpreter::drive().
     */
    public function drive(mixed $instance, mixed $model, string $event): mixed
    {
        if (! $model instanceof CmmnCaseModel) {
            throw new \InvalidArgumentException('CmmnInterpreter::drive() requires a parsed CmmnCaseModel.');
        }

        $currentNodeId = $instance->current_state ?: $model->startNodeId;

        $transition = $this->selectTransition($model->outgoingTransitions($currentNodeId), $event);

        if ($transition === null) {
            throw new \RuntimeException("No transition for event [{$event}] from state [{$currentNodeId}].");
        }

        return $transition->target;
    }

    /**
     * Matches $event against a named standardEvent first; falls back to
     * an entry criterion with no standardEvent so callers don't need to
     * name every trivial linear step.
     *
     * @param  list<CmmnTransition>  $transitions
     */
    private function selectTransition(array $transitions, string $event): ?CmmnTransition
    {
        foreach ($transitions as $transition) {
            if ($transition->standardEvent === $event) {
                return $transition;
            }
        }

        foreach ($transitions as $transition) {
            if ($transition->standardEvent === null) {
                return $transition;
            }
        }

        return null;
    }
}
