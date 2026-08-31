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
    private const TASK_ELEMENTS = [
        'task', 'userTask', 'serviceTask', 'manualTask', 'scriptTask', 'businessRuleTask', 'sendTask', 'receiveTask',
    ];

    /** @return BpmnProcessModel */
    public function parse(string $xml): mixed
    {
        $document = new \DOMDocument;
        $document->loadXML($xml);

        $xpath = new \DOMXPath($document);

        $roleByNodeId = $this->laneAssignments($xpath);

        $nodes = [];

        foreach ($this->queryElements($xpath, ['startEvent']) as $element) {
            $nodes[$element->getAttribute('id')] = $this->toNode($element, 'startEvent', $roleByNodeId);
        }

        foreach ($this->queryElements($xpath, self::TASK_ELEMENTS) as $element) {
            $nodes[$element->getAttribute('id')] = $this->toNode($element, 'task', $roleByNodeId);
        }

        foreach ($this->queryElements($xpath, ['endEvent']) as $element) {
            $nodes[$element->getAttribute('id')] = $this->toNode($element, 'endEvent', $roleByNodeId);
        }

        $startEventIds = [];

        foreach ($nodes as $node) {
            if ($node->type === 'startEvent') {
                $startEventIds[] = $node->id;
            }
        }

        if (count($startEventIds) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'BPMN XML must have exactly one start event; found %d.',
                count($startEventIds)
            ));
        }

        $startNodeId = $startEventIds[0];

        $flows = [];

        foreach ($this->queryElements($xpath, ['sequenceFlow']) as $element) {
            $flows[] = new BpmnFlow(
                id: $element->getAttribute('id'),
                source: $element->getAttribute('sourceRef'),
                target: $element->getAttribute('targetRef'),
                name: $element->getAttribute('name') ?: null,
            );
        }

        return new BpmnProcessModel($nodes, $flows, $startNodeId);
    }

    /** @param array<string, string> $roleByNodeId */
    private function toNode(\DOMElement $element, string $type, array $roleByNodeId): BpmnNode
    {
        $id = $element->getAttribute('id');

        return new BpmnNode(
            id: $id,
            type: $type,
            name: $element->getAttribute('name') ?: null,
            role: $roleByNodeId[$id] ?? null,
        );
    }

    /**
     * @param  list<string>  $localNames
     * @return list<\DOMElement>
     */
    private function queryElements(\DOMXPath $xpath, array $localNames): array
    {
        $conditions = implode(' or ', array_map(
            fn (string $name): string => "local-name()='{$name}'",
            $localNames,
        ));

        $result = $xpath->query("//*[{$conditions}]");

        if ($result === false) {
            return [];
        }

        return array_values(array_filter(
            iterator_to_array($result, false),
            fn ($node): bool => $node instanceof \DOMElement,
        ));
    }

    /** @return array<string, string> */
    private function laneAssignments(\DOMXPath $xpath): array
    {
        $roleByNodeId = [];

        $lanes = $xpath->query("//*[local-name()='lane']");

        if ($lanes === false) {
            return $roleByNodeId;
        }

        foreach ($lanes as $lane) {
            /** @var \DOMElement $lane */
            $role = $lane->getAttribute('name') ?: null;

            if ($role === null) {
                continue;
            }

            $refs = $xpath->query(".//*[local-name()='flowNodeRef']", $lane);

            if ($refs === false) {
                continue;
            }

            foreach ($refs as $ref) {
                $nodeId = trim($ref->textContent);

                if ($nodeId !== '') {
                    $roleByNodeId[$nodeId] = $role;
                }
            }
        }

        return $roleByNodeId;
    }
}
