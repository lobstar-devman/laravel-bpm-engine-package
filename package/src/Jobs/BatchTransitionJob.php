<?php

namespace Lobstar\BpmEngine\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lobstar\BpmEngine\Core\RevisionManager;

/**
 * One fixed-size batch of same-event, same-model-revision transitions —
 * see docs/arc42/06-runtime-view.md ("Bulk transition via queue") and
 * Core\QueueDispatcher, which groups and dispatches these. Loops over
 * its batch, invoking RevisionManager::transition() once per entity —
 * there is no separate batch API on RevisionManager, and the Event
 * Store still persists one event per transition (ADR-003).
 */
class BatchTransitionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param list<string> $instanceIds */
    public function __construct(
        public readonly array $instanceIds,
        public readonly string $event,
    ) {}

    public function handle(RevisionManager $revisionManager): void
    {
        foreach ($this->instanceIds as $instanceId) {
            $revisionManager->transition($instanceId, $this->event);
        }
    }
}
