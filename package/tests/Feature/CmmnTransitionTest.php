<?php

use Illuminate\Support\Facades\Event;
use Lobstar\BpmEngine\Core\EventStore;
use Lobstar\BpmEngine\Core\ModelRegistry;
use Lobstar\BpmEngine\Core\RevisionManager;
use Lobstar\BpmEngine\Events\TransitionRoleContext;
use Lobstar\BpmEngine\Models\Instance;

it('drives a case entity through a CMMN case plan, mirroring the BPMN transition scenario', function () {
    $xml = file_get_contents(__DIR__.'/../Fixtures/expense-dispute.cmmn');

    $registry = $this->app->make(ModelRegistry::class);
    $revision = $registry->store('cmmn', 'expense-dispute', $xml);

    $instance = Instance::create([
        'model_revision_id' => $revision->id,
        'type' => 'case',
        'current_state' => 'pi_review',
    ]);

    $revisionManager = $this->app->make(RevisionManager::class);

    $state = $revisionManager->transition($instance, 'complete');

    expect($state)->toBe('pi_resolve')
        ->and($instance->fresh()->current_state)->toBe('pi_resolve');

    $history = $this->app->make(EventStore::class)->history($instance);

    expect($history)->toHaveCount(1)
        ->and($history[0]->event_type)->toBe('complete');
});

it('dispatches TransitionRoleContext carrying the Case Role, after the transition is durably recorded (ADR-005)', function () {
    $xml = file_get_contents(__DIR__.'/../Fixtures/expense-dispute.cmmn');

    $registry = $this->app->make(ModelRegistry::class);
    $revision = $registry->store('cmmn', 'expense-dispute', $xml);

    $instance = Instance::create([
        'model_revision_id' => $revision->id,
        'type' => 'case',
        'current_state' => 'pi_review',
    ]);

    $dispatched = [];
    $persistedStateAtDispatchTime = null;

    Event::listen(TransitionRoleContext::class, function (TransitionRoleContext $event) use (&$dispatched, &$persistedStateAtDispatchTime) {
        $dispatched[] = $event;
        $persistedStateAtDispatchTime = Instance::find($event->instance->id)->current_state;
    });

    $revisionManager = $this->app->make(RevisionManager::class);
    $revisionManager->transition($instance, 'complete');

    expect($persistedStateAtDispatchTime)->toBe('pi_resolve')
        ->and($dispatched)->toHaveCount(1)
        ->and($dispatched[0]->standard)->toBe('cmmn')
        ->and($dispatched[0]->role)->toBe('Manager');
});
