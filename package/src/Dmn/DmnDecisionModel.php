<?php

namespace Lobstar\BpmEngine\Dmn;

/** The internal representation of a parsed DMN decision table, per DmnParser::parse(). */
final class DmnDecisionModel
{
    /**
     * @param  list<string>  $inputExpressions  the field of the input data tested by each input column, in column order
     * @param  list<string>  $outputNames  the output key produced by each output column, in column order
     * @param  list<DmnRule>  $rules
     */
    public function __construct(
        public readonly string $hitPolicy,
        public readonly array $inputExpressions,
        public readonly array $outputNames,
        public readonly array $rules,
    ) {}
}
