<?php

namespace App\Bpm\Adapters;

use App\Bpm\Contracts\BulkTransitionGateway;
use Lobstar\BpmEngine\Core\QueueDispatcher;

class PackageBulkTransitionGateway implements BulkTransitionGateway
{
    public function __construct(protected QueueDispatcher $queueDispatcher) {}

    public function dispatchBulk(iterable $instances, string $event): void
    {
        $this->queueDispatcher->dispatchBulk($instances, $event);
    }
}
