<?php

namespace Lobstar\BpmEngine\Cmmn;

/**
 * Parses CMMN 1.1 XML into the internal case representation.
 *
 * Per ADR-005, the parsed representation must capture, for each
 * activity, which Case Role it's assigned to (if any) — organizational
 * role metadata only, not an access-control mechanism — so
 * RevisionManager can report it via TransitionRoleContext.
 */
class CmmnParser
{
    public function parse(string $xml): mixed
    {
        throw new \RuntimeException('Not implemented yet.');
    }
}
