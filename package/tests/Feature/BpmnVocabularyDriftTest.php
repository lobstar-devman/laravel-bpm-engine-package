<?php

use Lobstar\BpmEngine\Core\ModelRegistry;

it('detects vocabulary drift between model revisions that rename a node id', function () {
    $xmlV1 = file_get_contents(__DIR__.'/../Fixtures/expense-process.bpmn');
    $xmlV2 = str_replace('task_review', 'task_review_v2', $xmlV1);

    $registry = $this->app->make(ModelRegistry::class);

    $registry->store('bpmn', 'expense-process', $xmlV1);
    $baselineHash = $registry->resolve('expense-process')->vocabulary()->hash();

    $registry->store('bpmn', 'expense-process', $xmlV2);
    $latestHash = $registry->resolve('expense-process')->vocabulary()->hash();

    expect($latestHash)->not->toBe($baselineHash);
});

it('does not report drift across revisions that only change cosmetic attributes', function () {
    $xmlV1 = file_get_contents(__DIR__.'/../Fixtures/expense-process.bpmn');
    $xmlV2 = file_get_contents(__DIR__.'/../Fixtures/expense-process-v2.bpmn');

    $registry = $this->app->make(ModelRegistry::class);

    $registry->store('bpmn', 'expense-process', $xmlV1);
    $baselineHash = $registry->resolve('expense-process')->vocabulary()->hash();

    $registry->store('bpmn', 'expense-process', $xmlV2);
    $latestHash = $registry->resolve('expense-process')->vocabulary()->hash();

    expect($latestHash)->toBe($baselineHash);
});
