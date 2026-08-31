<?php

namespace Lobstar\BpmEngine\Bpmn;

/**
 * A single BPMN flow node (start event, task, or end event) in the
 * internal process representation. Per ADR-005, $role is organizational
 * role/lane metadata only — never an access-control mechanism.
 */
final class BpmnNode
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?string $name,
        public readonly ?string $role,
    ) {}
}
