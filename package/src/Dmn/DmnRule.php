<?php

namespace Lobstar\BpmEngine\Dmn;

/** A single row of a DMN decision table: one unary test per input column, one literal per output column. */
final class DmnRule
{
    /**
     * @param  list<string>  $inputEntries
     * @param  list<string>  $outputEntries
     */
    public function __construct(
        public readonly array $inputEntries,
        public readonly array $outputEntries,
    ) {}
}
