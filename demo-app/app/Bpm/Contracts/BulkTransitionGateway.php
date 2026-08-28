<?php

namespace App\Bpm\Contracts;

interface BulkTransitionGateway
{
    /**
     * @param  iterable<mixed>  $instances
     */
    public function dispatchBulk(iterable $instances, string $event): void;
}
