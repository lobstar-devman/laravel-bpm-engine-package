<?php

namespace App\Bpm\Contracts;

use App\Bpm\ValueObjects\InstanceId;

interface RevisionGateway
{
    public function transition(InstanceId $instance, string $event): mixed;

    public function rollback(InstanceId $instance, int $targetRevision): mixed;
}
