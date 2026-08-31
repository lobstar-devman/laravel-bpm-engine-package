<?php

namespace Lobstar\BpmEngine\Bpmn;

/** A single BPMN sequence flow connecting two nodes. */
final class BpmnFlow
{
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly string $target,
        public readonly ?string $name,
    ) {}
}
