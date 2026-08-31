<?php

namespace Lobstar\BpmEngine\Cmmn;

/**
 * Parses CMMN 1.1 XML into the internal case representation.
 *
 * Per ADR-005, the parsed representation must capture, for each
 * activity, which Case Role it's assigned to (if any) — organizational
 * role metadata only, not an access-control mechanism — so
 * RevisionManager can report it via TransitionRoleContext.
 *
 * Mirrors BpmnParser's shape and scope: a single, linear casePlanModel
 * (one plan item with no entry criterion as the start, each other plan
 * item entered via exactly one sentry) — not full CMMN 1.1 coverage
 * (stages, discretionary items, if/repetition rules, multi-onPart AND/OR
 * sentries, etc.).
 */
class CmmnParser
{
    private const TASK_DEFINITION_ELEMENTS = ['humanTask', 'task', 'processTask', 'caseTask', 'milestone'];

    /** @return CmmnCaseModel */
    public function parse(string $xml): mixed
    {
        $document = new \DOMDocument;
        $document->loadXML($xml);

        $xpath = new \DOMXPath($document);

        $roleNameById = $this->roleNames($xpath, $document);
        $performerRefByDefinitionId = $this->performerRefs($xpath, $document);

        $planItemElements = $this->queryElements($xpath, ['planItem'], $document);

        if ($planItemElements === []) {
            throw new \InvalidArgumentException('CMMN XML has no planItem.');
        }

        $nodes = [];
        $transitions = [];
        $targetsWithEntryCriterion = [];

        foreach ($planItemElements as $planItem) {
            $id = $planItem->getAttribute('id');
            $performerRef = $performerRefByDefinitionId[$planItem->getAttribute('definitionRef')] ?? null;

            $nodes[$id] = new CmmnNode(
                id: $id,
                name: $planItem->getAttribute('name') ?: null,
                role: $performerRef === null ? null : ($roleNameById[$performerRef] ?? null),
            );

            $entryCriterion = $this->queryElements($xpath, ['entryCriterion'], $planItem)[0] ?? null;

            if ($entryCriterion === null) {
                continue;
            }

            $targetsWithEntryCriterion[] = $id;

            $sentry = $this->findById($xpath, $document, 'sentry', $entryCriterion->getAttribute('sentryRef'));

            if ($sentry === null) {
                continue;
            }

            foreach ($this->queryElements($xpath, ['planItemOnPart'], $sentry) as $onPart) {
                $transitions[] = new CmmnTransition(
                    source: $onPart->getAttribute('sourceRef'),
                    target: $id,
                    standardEvent: $this->firstText($xpath, $onPart, 'standardEvent'),
                );
            }
        }

        $startCandidates = array_values(array_diff(array_keys($nodes), $targetsWithEntryCriterion));

        if (count($startCandidates) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'CMMN XML must have exactly one plan item with no entry criterion to start from; found %d.',
                count($startCandidates)
            ));
        }

        return new CmmnCaseModel($nodes, $transitions, $startCandidates[0]);
    }

    private function findById(\DOMXPath $xpath, \DOMNode $context, string $localName, string $id): ?\DOMElement
    {
        foreach ($this->queryElements($xpath, [$localName], $context) as $element) {
            if ($element->getAttribute('id') === $id) {
                return $element;
            }
        }

        return null;
    }

    /** @return array<string, string> role id => name */
    private function roleNames(\DOMXPath $xpath, \DOMNode $context): array
    {
        $roles = [];

        foreach ($this->queryElements($xpath, ['role'], $context) as $role) {
            $id = $role->getAttribute('id');

            if ($id !== '') {
                $roles[$id] = $role->getAttribute('name') ?: $id;
            }
        }

        return $roles;
    }

    /** @return array<string, string> task-definition id => performerRef */
    private function performerRefs(\DOMXPath $xpath, \DOMNode $context): array
    {
        $refs = [];

        foreach ($this->queryElements($xpath, self::TASK_DEFINITION_ELEMENTS, $context) as $definition) {
            $id = $definition->getAttribute('id');
            $performerRef = $definition->getAttribute('performerRef');

            if ($id !== '' && $performerRef !== '') {
                $refs[$id] = $performerRef;
            }
        }

        return $refs;
    }

    private function firstText(\DOMXPath $xpath, \DOMNode $context, string $localName): ?string
    {
        $element = $this->queryElements($xpath, [$localName], $context)[0] ?? null;
        $text = $element === null ? null : trim($element->textContent);

        return $text === null || $text === '' ? null : $text;
    }

    /**
     * @param  list<string>  $localNames
     * @return list<\DOMElement>
     */
    private function queryElements(\DOMXPath $xpath, array $localNames, \DOMNode $context): array
    {
        $conditions = implode(' or ', array_map(
            fn (string $name): string => "local-name()='{$name}'",
            $localNames,
        ));

        $result = $xpath->query(".//*[{$conditions}]", $context);

        if ($result === false) {
            return [];
        }

        return array_values(array_filter(
            iterator_to_array($result, false),
            fn ($node): bool => $node instanceof \DOMElement,
        ));
    }
}
