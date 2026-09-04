<?php

namespace Tests\Fakes;

use App\Bpm\Contracts\DecisionGateway;

class FakeDecisionGateway implements DecisionGateway
{
    /** @var array<string, mixed> */
    public array $result = ['auto_approve' => false];

    /** @var array<int, array{decisionKey: string, inputData: array<string, mixed>}> */
    public array $calls = [];

    public function evaluate(string $decisionKey, array $inputData): array
    {
        $this->calls[] = ['decisionKey' => $decisionKey, 'inputData' => $inputData];

        return $this->result;
    }
}
