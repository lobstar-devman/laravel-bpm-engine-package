<?php

namespace Lobstar\BpmEngine\Core;

use Lobstar\BpmEngine\Jobs\BatchTransitionJob;
use Lobstar\BpmEngine\Models\Instance;

/**
 * Groups entities sharing the same triggering event and model revision
 * into fixed-size batches, dispatching one queue job per batch. See
 * docs/arc42/06-runtime-view.md ("Bulk transition via queue").
 */
class QueueDispatcher
{
    public function __construct(protected int $batchSize = 1000) {}

    /**
     * $event is already fixed for the whole call, so grouping "by
     * triggering event and model revision" (Section 6) reduces to
     * grouping $instances by model revision here — each resulting batch
     * then drives a single, homogeneous BpmnInterpreter model.
     */
    public function dispatchBulk(iterable $instances, string $event): void
    {
        foreach ($this->groupByRevision($instances) as $instanceIds) {
            foreach (array_chunk($instanceIds, $this->batchSize) as $batch) {
                BatchTransitionJob::dispatch($batch, $event);
            }
        }
    }

    /**
     * @param  iterable<mixed>  $instances  Instance models and/or instance ids
     * @return array<string, list<string>> instance ids grouped by model_revision_id
     */
    private function groupByRevision(iterable $instances): array
    {
        $grouped = [];
        $unresolvedIds = [];

        foreach ($instances as $instance) {
            if ($instance instanceof Instance) {
                $grouped[$instance->model_revision_id][] = $instance->id;

                continue;
            }

            $unresolvedIds[] = (string) $instance;
        }

        if ($unresolvedIds === []) {
            return $grouped;
        }

        /** @var array<string, string> $revisionById */
        $revisionById = Instance::whereIn('id', $unresolvedIds)->pluck('model_revision_id', 'id')->all();

        foreach ($unresolvedIds as $id) {
            if (! isset($revisionById[$id])) {
                throw new \RuntimeException("QueueDispatcher::dispatchBulk() could not resolve an Instance for id [{$id}].");
            }

            $grouped[$revisionById[$id]][] = $id;
        }

        return $grouped;
    }
}
