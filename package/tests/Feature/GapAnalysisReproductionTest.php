<?php

use Illuminate\Support\Facades\Queue;
use Lobstar\BpmEngine\Core\ModelRegistry;
use Lobstar\BpmEngine\Core\QueueDispatcher;
use Lobstar\BpmEngine\Core\RevisionManager;
use Lobstar\BpmEngine\Dmn\DmnEvaluator;
use Lobstar\BpmEngine\Jobs\BatchTransitionJob;
use Lobstar\BpmEngine\Models\Instance;

/**
 * Reproduction cases for three gaps demo-app reported against this
 * package (instance identity shape, DMN evaluator chaining, BPMN state
 * naming). See CLAUDE.md — these are written from the gap reports'
 * described symptoms/stack traces, not by reading demo-app's source.
 */
it('gap 1: accepts a hydrated Instance model directly, per already-existing RevisionManager/QueueDispatcher support', function () {
    Queue::fake();

    $xml = file_get_contents(__DIR__.'/../Fixtures/expense-process.bpmn');
    $revision = $this->app->make(ModelRegistry::class)->store('bpmn', 'expense-process', $xml);

    $instance = Instance::create([
        'model_revision_id' => $revision->id,
        'type' => 'process',
        'current_state' => 'start_1',
    ]);

    $revisionManager = $this->app->make(RevisionManager::class);

    // transition()/rollback() with a hydrated model — not a raw id.
    expect($revisionManager->transition($instance, 'submit'))->toBe('task_review');
    expect($revisionManager->rollback($instance, 1))->toBe('task_review');

    // dispatchBulk() with a collection of hydrated models — not raw ids.
    $this->app->make(QueueDispatcher::class)->dispatchBulk([$instance], 'submit');

    Queue::assertPushed(BatchTransitionJob::class);
});

it('gap 2: DmnEvaluator::evaluate() takes the decision key string, per the documented "evaluate(decisionKey, inputData)" sequence — not a resolved model object', function () {
    $xml = file_get_contents(__DIR__.'/../Fixtures/expense-approval.dmn');
    $this->app->make(ModelRegistry::class)->store('dmn', 'expense-approval', $xml);

    $evaluator = $this->app->make(DmnEvaluator::class);

    // The documented call: evaluate(decisionKey, inputData).
    expect($evaluator->evaluate('expense-approval', ['amount' => 50]))
        ->toBe(['requiresManagerApproval' => false]);

    // Passing the resolved model instead of the key is a contract
    // violation, not this package's bug — it contradicts the live
    // Section 6 diagram, which shows the Evaluator resolving the model
    // itself. Confirms this fails clearly rather than silently.
    $resolvedModel = $this->app->make(ModelRegistry::class)->resolve('expense-approval');

    expect(fn () => $evaluator->evaluate($resolvedModel, ['amount' => 50]))
        ->toThrow(InvalidArgumentException::class);
});

it('gap 3: BpmnInterpreter matches current_state against the model\'s actual node ids, not arbitrary domain state names', function () {
    $xml = file_get_contents(__DIR__.'/../Fixtures/expense-process.bpmn');
    $revision = $this->app->make(ModelRegistry::class)->store('bpmn', 'expense-process', $xml);

    // A domain-friendly name that isn't one of this model's real node
    // ids (start_1 / task_review / end_1) — by design, current_state
    // must be the BPMN element id as authored in the .bpmn XML.
    $instance = Instance::create([
        'model_revision_id' => $revision->id,
        'type' => 'process',
        'current_state' => 'submitted',
    ]);

    $revisionManager = $this->app->make(RevisionManager::class);

    expect(fn () => $revisionManager->transition($instance, 'submit'))
        ->toThrow(RuntimeException::class, 'No transition for event [submit] from state [submitted].');
});
