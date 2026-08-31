<?php

use Illuminate\Support\Facades\Event;
use Lobstar\BpmEngine\Core\EventStore;
use Lobstar\BpmEngine\Core\ModelRegistry;
use Lobstar\BpmEngine\Core\RevisionManager;
use Lobstar\BpmEngine\Events\TransitionRoleContext;
use Lobstar\BpmEngine\Models\Instance;
use Lobstar\BpmEngine\Models\TransitionEvent;

it('drives an entity through a BPMN process, per the Section 6 "Drive an entity through a BPMN process" scenario', function () {
    $xml = file_get_contents(__DIR__.'/../Fixtures/expense-process.bpmn');

    $registry = $this->app->make(ModelRegistry::class);
    $revision = $registry->store('bpmn', 'expense-process', $xml);

    $instance = Instance::create([
        'model_revision_id' => $revision->id,
        'type' => 'process',
        'current_state' => 'start_1',
    ]);

    $revisionManager = $this->app->make(RevisionManager::class);

    $state = $revisionManager->transition($instance, 'submit');

    expect($state)->toBe('task_review')
        ->and($instance->fresh()->current_state)->toBe('task_review');

    $state = $revisionManager->transition($instance, 'approve');

    expect($state)->toBe('end_1')
        ->and($instance->fresh()->current_state)->toBe('end_1');

    $history = $this->app->make(EventStore::class)->history($instance);

    expect($history)->toHaveCount(2)
        ->and($history[0]->event_type)->toBe('submit')
        ->and($history[1]->event_type)->toBe('approve');
});

it('dispatches TransitionRoleContext only after the transition is durably recorded, per ADR-005', function () {
    $xml = file_get_contents(__DIR__.'/../Fixtures/expense-process.bpmn');

    $registry = $this->app->make(ModelRegistry::class);
    $revision = $registry->store('bpmn', 'expense-process', $xml);

    $instance = Instance::create([
        'model_revision_id' => $revision->id,
        'type' => 'process',
        'current_state' => 'start_1',
    ]);

    $dispatched = [];
    $persistedStateAtDispatchTime = null;
    $persistedEventCountAtDispatchTime = null;

    Event::listen(TransitionRoleContext::class, function (TransitionRoleContext $event) use (&$dispatched, &$persistedStateAtDispatchTime, &$persistedEventCountAtDispatchTime) {
        $dispatched[] = $event;
        $persistedStateAtDispatchTime = Instance::find($event->instance->id)->current_state;
        $persistedEventCountAtDispatchTime = TransitionEvent::where('instance_id', $event->instance->id)->count();
    });

    $revisionManager = $this->app->make(RevisionManager::class);
    $revisionManager->transition($instance, 'submit');

    // Both the instance's projected state and the event log must already
    // reflect the transition by the time the listener runs — proving the
    // event fires after persistence, not before, per ADR-005.
    expect($persistedStateAtDispatchTime)->toBe('task_review')
        ->and($persistedEventCountAtDispatchTime)->toBe(1)
        ->and($dispatched)->toHaveCount(1)
        ->and($dispatched[0]->event)->toBe('submit')
        ->and($dispatched[0]->standard)->toBe('bpmn')
        ->and($dispatched[0]->role)->toBe('Reviewer');

    $revisionManager->transition($instance, 'approve');

    expect($dispatched)->toHaveCount(2)
        ->and($dispatched[1]->role)->toBeNull();
});
