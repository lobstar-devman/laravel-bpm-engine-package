<?php

namespace Lobstar\BpmEngine\Dmn;

/**
 * Parses DMN XML into the internal decision-table representation.
 *
 * Supports a single decision with a single decisionTable — enough to
 * drive Section 6's "Evaluate a DMN decision table" scenario — not full
 * DMN 1.3 coverage (multiple decisions, decision requirement graphs,
 * boxed expressions, etc.).
 */
class DmnParser
{
    /** @return DmnDecisionModel */
    public function parse(string $xml): mixed
    {
        $document = new \DOMDocument;
        $document->loadXML($xml);

        $xpath = new \DOMXPath($document);

        $decisionTables = $this->queryElements($xpath, ['decisionTable'], $document);

        if (count($decisionTables) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'DMN XML must have exactly one decisionTable; found %d.',
                count($decisionTables)
            ));
        }

        $decisionTable = $decisionTables[0];

        $hitPolicy = $decisionTable->getAttribute('hitPolicy') ?: 'FIRST';

        $inputExpressions = [];

        foreach ($this->queryElements($xpath, ['input'], $decisionTable) as $input) {
            $inputExpressions[] = $this->firstText($xpath, $input);
        }

        $outputNames = [];

        foreach ($this->queryElements($xpath, ['output'], $decisionTable) as $output) {
            $outputNames[] = $output->getAttribute('name');
        }

        $rules = [];

        foreach ($this->queryElements($xpath, ['rule'], $decisionTable) as $ruleElement) {
            $rules[] = new DmnRule(
                inputEntries: $this->entryTexts($xpath, $ruleElement, 'inputEntry'),
                outputEntries: $this->entryTexts($xpath, $ruleElement, 'outputEntry'),
            );
        }

        return new DmnDecisionModel($hitPolicy, $inputExpressions, $outputNames, $rules);
    }

    /** @return list<string> */
    private function entryTexts(\DOMXPath $xpath, \DOMElement $ruleElement, string $entryTag): array
    {
        $texts = [];

        foreach ($this->queryElements($xpath, [$entryTag], $ruleElement) as $entry) {
            $texts[] = $this->firstText($xpath, $entry);
        }

        return $texts;
    }

    private function firstText(\DOMXPath $xpath, \DOMNode $context): string
    {
        $textElement = $this->queryElements($xpath, ['text'], $context)[0] ?? null;

        return $textElement === null ? '' : trim($textElement->textContent);
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
