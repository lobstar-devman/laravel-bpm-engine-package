<?php

use Lobstar\BpmEngine\Bpmn\BpmnProcessModel;
use Lobstar\BpmEngine\Core\ModelRegistry;
use Lobstar\BpmEngine\Models\ModelDefinition;
use Lobstar\BpmEngine\Models\ModelRevision;

it('stores a new definition and revision, then resolves and parses it', function () {
    $xml = file_get_contents(__DIR__.'/../../Fixtures/expense-process.bpmn');

    $registry = $this->app->make(ModelRegistry::class);

    $revision = $registry->store('bpmn', 'expense-process', $xml);

    expect($revision)->toBeInstanceOf(ModelRevision::class)
        ->and($revision->revision_number)->toBe(1)
        ->and(ModelDefinition::where('key', 'expense-process')->exists())->toBeTrue();

    $model = $registry->resolve('expense-process');

    expect($model)->toBeInstanceOf(BpmnProcessModel::class)
        ->and($model->startNodeId)->toBe('start_1');
});

it('resolves the latest revision by default and a specific one when given', function () {
    $xmlV1 = file_get_contents(__DIR__.'/../../Fixtures/expense-process.bpmn');
    $xmlV2 = str_replace('Review Expense', 'Review Expense (v2)', $xmlV1);

    $registry = $this->app->make(ModelRegistry::class);
    $registry->store('bpmn', 'expense-process', $xmlV1);
    $registry->store('bpmn', 'expense-process', $xmlV2);

    $latest = $registry->resolve('expense-process');
    $first = $registry->resolve('expense-process', 1);

    expect($latest->node('task_review')->name)->toBe('Review Expense (v2)')
        ->and($first->node('task_review')->name)->toBe('Review Expense');
});

it('caches the parsed model per revision', function () {
    $xml = file_get_contents(__DIR__.'/../../Fixtures/expense-process.bpmn');

    $registry = $this->app->make(ModelRegistry::class);
    $registry->store('bpmn', 'expense-process', $xml);

    $first = $registry->resolve('expense-process');
    $second = $registry->resolve('expense-process');

    expect($first)->toBe($second);
});
