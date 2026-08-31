<?php

use Illuminate\Support\Facades\Event;
use Lobstar\BpmEngine\Core\EventStore;
use Lobstar\BpmEngine\Core\ModelRegistry;
use Lobstar\BpmEngine\Core\RevisionManager;
use Lobstar\BpmEngine\Events\TransitionRoleContext;
use Lobstar\BpmEngine\Models\Instance;
use Lobstar\BpmEngine\Models\TransitionEvent;

it('rolls an entity back to a prior model revision, per the Section 6 "Roll back" scenario', function () {
    $xmlV1 = file_get_contents(__DIR__.'/../Fixtures/expense-process.bpmn');
    $xmlV2 = file_get_contents(__DIR__.'/../Fixtures/expense-process-v2.bpmn');

    $registry = $this->app->make(ModelRegistry::class);
    $revisionV1 = $registry->store('bpmn', 'expense-process', $xmlV1);
    $revisionV2 = $registry->store('bpmn', 'expense-process', $xmlV2);

    // The instance is currently being driven through revision 2.
    $instance = Instance::create([
        'model_revision_id' => $revisionV2->id,
        'type' => 'process',
        'current_state' => 'start_1',
    ]);

    $revisionManager = $this->app->make(RevisionManager::class);
    $revisionManager->transition($instance, 'submit');

    expect($instance->fresh()->current_state)->toBe('task_review')
        ->and($instance->fresh()->model_revision_id)->toBe($revisionV2->id);

    $restoredState = $revisionManager->rollback($instance, 1);

    expect($restoredState)->toBe('task_review')
        ->and($instance->fresh()->model_revision_id)->toBe($revisionV1->id)
        ->and($instance->fresh()->current_state)->toBe('task_review');

    $history = $this->app->make(EventStore::class)->history($instance);

    expect($history)->toHaveCount(2)
        ->and($history[0]->event_type)->toBe('submit')
        ->and($history[1]->event_type)->toBe('RolledBackEvent')
        ->and($history[1]->payload)->toBe([
            'from_revision' => 2,
            'to_revision' => 1,
            'state' => 'task_review',
        ]);
});

it('recomputes state against the target revision, not the one being rolled back from', function () {
    $xmlV1 = file_get_contents(__DIR__.'/../Fixtures/expense-process.bpmn');
    $xmlV2 = file_get_contents(__DIR__.'/../Fixtures/expense-process-v2.bpmn');

    $registry = $this->app->make(ModelRegistry::class);
    $registry->store('bpmn', 'expense-process', $xmlV1);
    $revisionV2 = $registry->store('bpmn', 'expense-process', $xmlV2);

    $instance = Instance::create([
        'model_revision_id' => $revisionV2->id,
        'type' => 'process',
        'current_state' => 'start_1',
    ]);

    $revisionManager = $this->app->make(RevisionManager::class);
    $revisionManager->transition($instance, 'submit');

    $dispatched = [];
    Event::listen(TransitionRoleContext::class, function (TransitionRoleContext $event) use (&$dispatched) {
        $dispatched[] = $event;
    });

    $revisionManager->rollback($instance, 1);

    expect($dispatched)->toHaveCount(1)
        ->and($dispatched[0]->event)->toBe('RolledBackEvent')
        ->and($dispatched[0]->role)->toBe('Reviewer');
});

it('dispatches TransitionRoleContext only after the rollback is durably recorded, per ADR-005', function () {
    $xmlV1 = file_get_contents(__DIR__.'/../Fixtures/expense-process.bpmn');
    $xmlV2 = file_get_contents(__DIR__.'/../Fixtures/expense-process-v2.bpmn');

    $registry = $this->app->make(ModelRegistry::class);
    $revisionV1 = $registry->store('bpmn', 'expense-process', $xmlV1);
    $revisionV2 = $registry->store('bpmn', 'expense-process', $xmlV2);

    $instance = Instance::create([
        'model_revision_id' => $revisionV2->id,
        'type' => 'process',
        'current_state' => 'start_1',
    ]);

    $revisionManager = $this->app->make(RevisionManager::class);
    $revisionManager->transition($instance, 'submit');

    $persistedRevisionAtDispatchTime = null;
    $persistedStateAtDispatchTime = null;
    $persistedEventCountAtDispatchTime = null;

    Event::listen(TransitionRoleContext::class, function (TransitionRoleContext $event) use (

        &$persistedRevisionAtDispatchTime,
        &$persistedStateAtDispatchTime,
        &$persistedEventCountAtDispatchTime
    ) {
        // Ignore the earlier transition()'s own dispatch; only capture the rollback's.
        if ($event->event !== 'RolledBackEvent') {
            return;
        }

        $fresh = Instance::find($event->instance->id);
        $persistedRevisionAtDispatchTime = $fresh->model_revision_id;
        $persistedStateAtDispatchTime = $fresh->current_state;
        $persistedEventCountAtDispatchTime = TransitionEvent::where('instance_id', $event->instance->id)->count();
    });

    $revisionManager->rollback($instance, 1);

    expect($persistedRevisionAtDispatchTime)->toBe($revisionV1->id)
        ->and($persistedStateAtDispatchTime)->toBe('task_review')
        ->and($persistedEventCountAtDispatchTime)->toBe(2);
});
