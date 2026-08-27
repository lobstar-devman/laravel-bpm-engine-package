<?php

namespace Lobstar\BpmEngine\Bpmn;

/** Drives entities through a parsed BPMN process model. */
class BpmnInterpreter
{
    public function drive(mixed $instance, mixed $model, string $event): mixed
    {
        throw new \RuntimeException('Not implemented yet.');
    }
}
