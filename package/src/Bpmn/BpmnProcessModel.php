<?php

namespace Lobstar\BpmEngine\Bpmn;

/** The internal representation of a parsed BPMN process, per BpmnParser::parse(). */
final class BpmnProcessModel
{
    /**
     * @param  array<string, BpmnNode>  $nodes
     * @param  list<BpmnFlow>  $flows
     */
    public function __construct(
        public readonly array $nodes,
        public readonly array $flows,
        public readonly string $startNodeId,
    ) {}

    public function node(string $id): BpmnNode
    {
        return $this->nodes[$id] ?? throw new \InvalidArgumentException("Unknown BPMN node [{$id}].");
    }

    /** @return list<BpmnFlow> */
    public function outgoingFlows(string $nodeId): array
    {
        return array_values(array_filter(
            $this->flows,
            fn (BpmnFlow $flow): bool => $flow->source === $nodeId,
        ));
    }
}
