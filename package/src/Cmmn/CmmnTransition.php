<?php

namespace Lobstar\BpmEngine\Cmmn;

/**
 * A single CMMN entry criterion: $target's sentry fires once $source
 * reaches its $standardEvent (e.g. "complete") — CMMN's equivalent of a
 * BPMN sequence flow, derived from a <planItemOnPart>/<sentry> pair.
 */
final class CmmnTransition
{
    public function __construct(
        public readonly string $source,
        public readonly string $target,
        public readonly ?string $standardEvent,
    ) {}
}
