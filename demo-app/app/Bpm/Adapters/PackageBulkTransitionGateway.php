<?php

namespace App\Bpm\Adapters;

use App\Bpm\Contracts\BulkTransitionGateway;
use Lobstar\BpmEngine\Core\QueueDispatcher;

class PackageBulkTransitionGateway implements BulkTransitionGateway
{
    public function __construct(protected QueueDispatcher $queueDispatcher) {}

    public function dispatchBulk(iterable $instances, string $event): void
    {
        $ids = [];

        foreach ($instances as $instance) {
            $ids[] = (string) $instance;
        }

        $this->queueDispatcher->dispatchBulk($ids, $event);
    }
}
