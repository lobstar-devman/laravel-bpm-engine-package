<?php

namespace Lobstar\BpmEngine\Cmmn;

/**
 * A single CMMN plan item in the internal case representation. Per
 * ADR-005, $role is organizational Case Role metadata only — never an
 * access-control mechanism.
 */
final class CmmnNode
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name,
        public readonly ?string $role,
    ) {}
}
