<?php

namespace App\Bpm\Contracts;

use App\Bpm\ValueObjects\InstanceId;

interface BulkTransitionGateway
{
    /**
     * @param  iterable<InstanceId>  $instances
     */
    public function dispatchBulk(iterable $instances, string $event): void;
}
