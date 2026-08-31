<?php

namespace Lobstar\BpmEngine\Bpmn;

/** Drives entities through a parsed BPMN process model. */
class BpmnInterpreter
{
    /**
     * Advances $instance (anything exposing a `current_state` property,
     * null/empty meaning "not yet started") one step through $model along
     * the outgoing sequence flow matching $event, and returns the id of
     * the resulting node.
     */
    public function drive(mixed $instance, mixed $model, string $event): mixed
    {
        if (! $model instanceof BpmnProcessModel) {
            throw new \InvalidArgumentException('BpmnInterpreter::drive() requires a parsed BpmnProcessModel.');
        }

        $currentNodeId = $instance->current_state ?: $model->startNodeId;

        $flow = $this->selectFlow($model->outgoingFlows($currentNodeId), $event);

        if ($flow === null) {
            throw new \RuntimeException("No transition for event [{$event}] from state [{$currentNodeId}].");
        }

        return $flow->target;
    }

    /**
     * Matches $event against a named outgoing flow first; falls back to
     * an unnamed flow (e.g. a start event's single, unconditional flow)
     * so callers don't need to name every trivial linear step.
     *
     * @param  list<BpmnFlow>  $flows
     */
    private function selectFlow(array $flows, string $event): ?BpmnFlow
    {
        foreach ($flows as $flow) {
            if ($flow->name === $event) {
                return $flow;
            }
        }

        foreach ($flows as $flow) {
            if ($flow->name === null) {
                return $flow;
            }
        }

        return null;
    }
}
