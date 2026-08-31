<?php

use Lobstar\BpmEngine\Core\EventStore;
use Lobstar\BpmEngine\Models\Instance;
use Lobstar\BpmEngine\Models\ModelDefinition;
use Lobstar\BpmEngine\Models\ModelRevision;
use Lobstar\BpmEngine\Models\TransitionEvent;

beforeEach(function () {
    $definition = ModelDefinition::create(['standard' => 'bpmn', 'key' => 'k', 'name' => 'K']);
    $revision = ModelRevision::create(['model_definition_id' => $definition->id, 'revision_number' => 1, 'xml' => '<x/>']);

    $this->instance = Instance::create(['model_revision_id' => $revision->id, 'type' => 'process', 'current_state' => 'start']);
});

it('appends one transition_events row per call, one write per transition per ADR-003', function () {
    $eventStore = $this->app->make(EventStore::class);

    $eventStore->append($this->instance, 'submit', ['to' => 'task_review']);
    $eventStore->append($this->instance, 'approve', ['to' => 'end_1']);

    expect(TransitionEvent::where('instance_id', $this->instance->id)->count())->toBe(2);
});

it('returns the instance history in occurrence order', function () {
    $eventStore = $this->app->make(EventStore::class);

    $eventStore->append($this->instance, 'submit', ['to' => 'task_review']);
    $eventStore->append($this->instance, 'approve', ['to' => 'end_1']);

    $history = $eventStore->history($this->instance);

    expect($history)->toHaveCount(2)
        ->and($history[0]->event_type)->toBe('submit')
        ->and($history[0]->payload)->toBe(['to' => 'task_review'])
        ->and($history[1]->event_type)->toBe('approve');
});
