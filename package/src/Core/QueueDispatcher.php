<?php

namespace Lobstar\BpmEngine\Core;

/**
 * Groups entities sharing the same triggering event and model revision
 * into fixed-size batches, dispatching one queue job per batch. See
 * docs/arc42/06-runtime-view.md ("Bulk transition via queue").
 */
class QueueDispatcher
{
    public function __construct(protected int $batchSize = 1000)
    {
    }

    public function dispatchBulk(iterable $instances, string $event): void
    {
        throw new \RuntimeException('Not implemented yet.');
    }
}
