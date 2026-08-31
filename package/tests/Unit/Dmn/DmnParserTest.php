<?php

use Lobstar\BpmEngine\Dmn\DmnDecisionModel;
use Lobstar\BpmEngine\Dmn\DmnParser;

it('parses a decision table into hit policy, input/output columns, and rules', function () {
    $xml = file_get_contents(__DIR__.'/../../Fixtures/expense-approval.dmn');

    $model = (new DmnParser)->parse($xml);

    expect($model)->toBeInstanceOf(DmnDecisionModel::class)
        ->and($model->hitPolicy)->toBe('FIRST')
        ->and($model->inputExpressions)->toBe(['amount'])
        ->and($model->outputNames)->toBe(['requiresManagerApproval'])
        ->and($model->rules)->toHaveCount(2)
        ->and($model->rules[0]->inputEntries)->toBe(['<= 100'])
        ->and($model->rules[0]->outputEntries)->toBe(['false'])
        ->and($model->rules[1]->inputEntries)->toBe(['-'])
        ->and($model->rules[1]->outputEntries)->toBe(['true']);
});

it('throws for XML with no decisionTable', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <definitions xmlns="https://www.omg.org/spec/DMN/20191111/MODEL/">
      <decision id="broken" name="Broken"/>
    </definitions>
    XML;

    (new DmnParser)->parse($xml);
})->throws(InvalidArgumentException::class);
