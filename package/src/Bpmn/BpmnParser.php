<?php

namespace Lobstar\BpmEngine\Bpmn;

/**
 * Parses BPMN 2.0 XML into the internal process representation.
 *
 * Per ADR-005, the parsed representation must capture, for each
 * activity, which Lane/Pool it's assigned to (if any) — organizational
 * role metadata only, not an access-control mechanism — so
 * RevisionManager can report it via TransitionRoleContext.
 */
class BpmnParser
{
    public function parse(string $xml): mixed
    {
        throw new \RuntimeException('Not implemented yet.');
    }
}
