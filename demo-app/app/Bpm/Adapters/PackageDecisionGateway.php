<?php

namespace App\Bpm\Adapters;

use App\Bpm\Contracts\DecisionGateway;
use Lobstar\BpmEngine\Dmn\DmnEvaluator;

class PackageDecisionGateway implements DecisionGateway
{
    public function __construct(protected DmnEvaluator $dmnEvaluator) {}

    public function evaluate(string $decisionKey, array $inputData): array
    {
        return $this->dmnEvaluator->evaluate($decisionKey, $inputData);
    }
}
