<?php

use Illuminate\Support\Facades\Queue;
use Lobstar\BpmEngine\Core\ModelRegistry;
use Lobstar\BpmEngine\Core\QueueDispatcher;
use Lobstar\BpmEngine\Core\RevisionManager;
use Lobstar\BpmEngine\Jobs\BatchTransitionJob;
use Lobstar\BpmEngine\Models\Instance;

beforeEach(function () {
    $xmlV1 = file_get_contents(__DIR__.'/../Fixtures/expense-process.bpmn');
    $xmlV2 = file_get_contents(__DIR__.'/../Fixtures/expense-process-v2.bpmn');

    $registry = $this->app->make(ModelRegistry::class);
    $this->revisionV1 = $registry->store('bpmn', 'expense-process', $xmlV1);
    $this->revisionV2 = $registry->store('bpmn', 'expense-process', $xmlV2);
});

it('groups instances by model revision and splits each group into fixed-size batches, per Section 6 "Bulk transition via queue"', function () {
    Queue::fake();

    $revision1Instances = collect(range(1, 3))->map(fn () => Instance::create([
        'model_revision_id' => $this->revisionV1->id,
        'type' => 'process',
        'current_state' => 'start_1',
    ]));

    $revision2Instances = collect(range(1, 2))->map(fn () => Instance::create([
        'model_revision_id' => $this->revisionV2->id,
        'type' => 'process',
        'current_state' => 'start_1',
    ]));

    $dispatcher = new QueueDispatcher(batchSize: 2);
    $dispatcher->dispatchBulk($revision1Instances->merge($revision2Instances), 'submit');

    // Revision 1 (3 instances, batch size 2) -> 2 jobs; revision 2 (2 instances) -> 1 job.
    Queue::assertPushed(BatchTransitionJob::class, 3);

    $dispatchedIds = [];

    Queue::assertPushed(function (BatchTransitionJob $job) use (&$dispatchedIds) {
        expect(count($job->instanceIds))->toBeLessThanOrEqual(2)
            ->and($job->event)->toBe('submit');

        foreach ($job->instanceIds as $id) {
            $dispatchedIds[] = $id;
        }

        return true;
    });

    expect($dispatchedIds)->toHaveCount(5)
        ->and(array_unique($dispatchedIds))->toHaveCount(5);
});

it('drives every dispatched batch end to end when the queue worker runs it', function () {
    Queue::fake();

    $instances = collect(range(1, 3))->map(fn () => Instance::create([
        'model_revision_id' => $this->revisionV1->id,
        'type' => 'process',
        'current_state' => 'start_1',
    ]));

    $dispatcher = $this->app->make(QueueDispatcher::class);
    $dispatcher->dispatchBulk($instances, 'submit');

    Queue::assertPushed(BatchTransitionJob::class, function (BatchTransitionJob $job) {
        // Simulate the queue worker executing the batch.
        $job->handle($this->app->make(RevisionManager::class));

        return true;
    });

    $instances->each(
        fn (Instance $instance) => expect($instance->fresh()->current_state)->toBe('task_review')
    );
});

it('resolves raw instance ids (not just Instance models) via a single lookup query', function () {
    Queue::fake();

    $instance = Instance::create([
        'model_revision_id' => $this->revisionV1->id,
        'type' => 'process',
        'current_state' => 'start_1',
    ]);

    $dispatcher = new QueueDispatcher(batchSize: 10);
    $dispatcher->dispatchBulk([$instance->id], 'submit');

    Queue::assertPushed(BatchTransitionJob::class, fn (BatchTransitionJob $job) => $job->instanceIds === [$instance->id]);
});

it('throws when a raw instance id cannot be resolved', function () {
    $dispatcher = new QueueDispatcher(batchSize: 10);

    $dispatcher->dispatchBulk(['does-not-exist'], 'submit');
})->throws(RuntimeException::class);

it('dispatches nothing for an empty instance list', function () {
    Queue::fake();

    (new QueueDispatcher)->dispatchBulk([], 'submit');

    Queue::assertNothingPushed();
});
