<?php

namespace Lobstar\BpmEngine\Cmmn;

/** The internal representation of a parsed CMMN case plan, per CmmnParser::parse(). */
final class CmmnCaseModel
{
    /**
     * @param  array<string, CmmnNode>  $nodes  plan items, keyed by planItem id
     * @param  list<CmmnTransition>  $transitions
     */
    public function __construct(
        public readonly array $nodes,
        public readonly array $transitions,
        public readonly string $startNodeId,
    ) {}

    public function node(string $id): CmmnNode
    {
        return $this->nodes[$id] ?? throw new \InvalidArgumentException("Unknown CMMN plan item [{$id}].");
    }

    /** @return list<CmmnTransition> */
    public function outgoingTransitions(string $nodeId): array
    {
        return array_values(array_filter(
            $this->transitions,
            fn (CmmnTransition $transition): bool => $transition->source === $nodeId,
        ));
    }
}
