<?php

namespace Lobstar\BpmEngine\Dmn;

use Illuminate\Support\Str;
use Lobstar\BpmEngine\Core\ModelRegistry;
use Lobstar\BpmEngine\Models\DecisionLog;
use Lobstar\BpmEngine\Models\ModelDefinition;
use Lobstar\BpmEngine\Models\ModelRevision;

/**
 * Evaluates a parsed DMN decision model against input data.
 *
 * Per docs/arc42/06-runtime-view.md ("Evaluate a DMN decision table"),
 * this is the top-level entry point the host app calls directly with
 * evaluate(decisionKey, inputData) — unlike BPMN, no separate Core
 * component orchestrates DMN evaluation, so DmnEvaluator itself resolves
 * the decision via the Model Registry (parsing/caching via DmnParser)
 * and writes a Decision Log row recording the inputs, outputs, and
 * model revision used, per docs/arc42/08-crosscutting-concepts.md.
 */
class DmnEvaluator
{
    private const SUPPORTED_HIT_POLICY = 'FIRST';

    public function __construct(
        private readonly ModelRegistry $modelRegistry,
    ) {}

    /**
     * $model is the decision's key (string), as passed to
     * ModelRegistry::resolve()/store() — not a resolved model. Declared
     * mixed (not string) precisely so a wrong type is caught below with
     * a clear message instead of PHP's own type-coercion error.
     */
    public function evaluate(mixed $model, array $inputData): array
    {
        if (! is_string($model)) {
            throw new \InvalidArgumentException(
                'DmnEvaluator::evaluate() takes the decision key (string), as passed to ModelRegistry::resolve()/store() — '
                .'not a resolved model. DmnEvaluator resolves the model itself; see Section 6, "Evaluate a DMN decision table".'
            );
        }

        $decisionKey = $model;

        $definition = ModelDefinition::where('key', $decisionKey)->firstOrFail();

        $revision = ModelRevision::where('model_definition_id', $definition->id)
            ->orderByDesc('revision_number')
            ->firstOrFail();

        $decisionModel = $this->modelRegistry->resolve($decisionKey);

        if (! $decisionModel instanceof DmnDecisionModel) {
            throw new \InvalidArgumentException('DmnEvaluator::evaluate() requires a parsed DmnDecisionModel.');
        }

        $outputs = $this->evaluateRules($decisionModel, $inputData);

        DecisionLog::create([
            'id' => (string) Str::uuid(),
            'model_revision_id' => $revision->id,
            'instance_id' => null,
            'inputs' => $inputData,
            'outputs' => $outputs,
        ]);

        return $outputs;
    }

    /** @return array<string, mixed> */
    private function evaluateRules(DmnDecisionModel $model, array $inputData): array
    {
        if ($model->hitPolicy !== self::SUPPORTED_HIT_POLICY) {
            throw new \RuntimeException(
                'DmnEvaluator only supports the ['.self::SUPPORTED_HIT_POLICY."] hit policy currently; got [{$model->hitPolicy}]."
            );
        }

        foreach ($model->rules as $rule) {
            if ($this->matches($rule, $model->inputExpressions, $inputData)) {
                return $this->outputs($rule, $model->outputNames);
            }
        }

        return [];
    }

    private function matches(DmnRule $rule, array $inputExpressions, array $inputData): bool
    {
        foreach ($rule->inputEntries as $index => $entry) {
            $field = $inputExpressions[$index] ?? null;
            $value = $field === null ? null : ($inputData[$field] ?? null);

            if (! $this->matchesUnaryTest($entry, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A minimal FEEL unary test: "-" (any), a comparison operator against
     * a number, or an exact literal match — enough for a first decision
     * table, not the full FEEL grammar (ranges, lists, "not(...)", etc.).
     */
    private function matchesUnaryTest(string $entry, mixed $value): bool
    {
        $entry = trim($entry);

        if ($entry === '' || $entry === '-') {
            return true;
        }

        if (preg_match('/^(<=|>=|<|>)\s*(-?\d+(?:\.\d+)?)$/', $entry, $matches) === 1) {
            if (! is_numeric($value)) {
                return false;
            }

            $bound = (float) $matches[2];
            $numericValue = (float) $value;

            return match ($matches[1]) {
                '<' => $numericValue < $bound,
                '<=' => $numericValue <= $bound,
                '>' => $numericValue > $bound,
                '>=' => $numericValue >= $bound,
            };
        }

        return $this->literal($entry) === $value;
    }

    /**
     * @param  list<string>  $outputNames
     * @return array<string, mixed>
     */
    private function outputs(DmnRule $rule, array $outputNames): array
    {
        $outputs = [];

        foreach ($rule->outputEntries as $index => $entry) {
            $name = $outputNames[$index] ?? (string) $index;
            $outputs[$name] = $this->literal($entry);
        }

        return $outputs;
    }

    private function literal(string $text): mixed
    {
        $text = trim($text);

        return match (true) {
            $text === 'true' => true,
            $text === 'false' => false,
            is_numeric($text) => str_contains($text, '.') ? (float) $text : (int) $text,
            default => trim($text, '"'),
        };
    }
}
