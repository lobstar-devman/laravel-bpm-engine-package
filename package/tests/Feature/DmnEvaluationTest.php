<?php

use Lobstar\BpmEngine\Core\ModelRegistry;
use Lobstar\BpmEngine\Dmn\DmnEvaluator;
use Lobstar\BpmEngine\Models\DecisionLog;

beforeEach(function () {
    $xml = file_get_contents(__DIR__.'/../Fixtures/expense-approval.dmn');

    $registry = $this->app->make(ModelRegistry::class);
    $this->revision = $registry->store('dmn', 'expense-approval', $xml);
});

it('evaluates a decision table against input data, per the Section 6 "Evaluate a DMN decision table" scenario', function () {
    $evaluator = $this->app->make(DmnEvaluator::class);

    expect($evaluator->evaluate('expense-approval', ['amount' => 50]))
        ->toBe(['requiresManagerApproval' => false]);

    expect($evaluator->evaluate('expense-approval', ['amount' => 500]))
        ->toBe(['requiresManagerApproval' => true]);
});

it('writes a Decision Log row per evaluation, recording inputs, outputs, and the model revision used', function () {
    $evaluator = $this->app->make(DmnEvaluator::class);

    $outputs = $evaluator->evaluate('expense-approval', ['amount' => 500]);

    expect(DecisionLog::count())->toBe(1);

    $log = DecisionLog::sole();

    expect($log->model_revision_id)->toBe($this->revision->id)
        ->and($log->instance_id)->toBeNull()
        ->and($log->inputs)->toBe(['amount' => 500])
        ->and($log->outputs)->toBe($outputs)
        ->and($log->outputs)->toBe(['requiresManagerApproval' => true]);
});

it('logs the latest revision when the decision has been updated', function () {
    $xmlV2 = str_replace('&lt;= 100', '&lt;= 200', file_get_contents(__DIR__.'/../Fixtures/expense-approval.dmn'));

    $registry = $this->app->make(ModelRegistry::class);
    $revisionV2 = $registry->store('dmn', 'expense-approval', $xmlV2);

    $evaluator = $this->app->make(DmnEvaluator::class);

    $outputs = $evaluator->evaluate('expense-approval', ['amount' => 150]);

    expect($outputs)->toBe(['requiresManagerApproval' => false]);

    $log = DecisionLog::sole();

    expect($log->model_revision_id)->toBe($revisionV2->id);
});
