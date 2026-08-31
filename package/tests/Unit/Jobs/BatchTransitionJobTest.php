<?php

use Lobstar\BpmEngine\Core\ModelRegistry;
use Lobstar\BpmEngine\Core\RevisionManager;
use Lobstar\BpmEngine\Jobs\BatchTransitionJob;
use Lobstar\BpmEngine\Models\Instance;

it('drives every instance in its batch through RevisionManager::transition(), one call per entity', function () {
    $xml = file_get_contents(__DIR__.'/../../Fixtures/expense-process.bpmn');

    $registry = $this->app->make(ModelRegistry::class);
    $revision = $registry->store('bpmn', 'expense-process', $xml);

    $instances = collect(range(1, 3))->map(fn () => Instance::create([
        'model_revision_id' => $revision->id,
        'type' => 'process',
        'current_state' => 'start_1',
    ]));

    $job = new BatchTransitionJob($instances->pluck('id')->all(), 'submit');
    $job->handle($this->app->make(RevisionManager::class));

    $instances->each(
        fn (Instance $instance) => expect($instance->fresh()->current_state)->toBe('task_review')
    );
});
