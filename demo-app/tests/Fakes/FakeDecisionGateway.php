<?php

namespace Tests\Fakes;

use App\Bpm\Contracts\DecisionGateway;

class FakeDecisionGateway implements DecisionGateway
{
    /** @var array<string, mixed> */
    public array $result = ['auto_approve' => false];

    /** @var array<int, array{model: mixed, inputData: array<string, mixed>}> */
    public array $calls = [];

    public function evaluate(mixed $model, array $inputData): array
    {
        $this->calls[] = ['model' => $model, 'inputData' => $inputData];

        return $this->result;
    }
}
