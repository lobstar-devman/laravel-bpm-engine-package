<?php

namespace App\Bpm\Contracts;

interface DecisionGateway
{
    /**
     * @param  array<string, mixed>  $inputData
     * @return array<string, mixed>
     */
    public function evaluate(mixed $model, array $inputData): array;
}
