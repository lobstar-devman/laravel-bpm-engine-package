<?php

namespace Tests\Fakes;

use App\Bpm\Contracts\RevisionGateway;
use App\Bpm\ValueObjects\InstanceId;

class FakeRevisionGateway implements RevisionGateway
{
    /** @var array<int, array{instance: InstanceId, event: string}> */
    public array $transitions = [];

    /** @var array<int, array{instance: InstanceId, targetRevision: int}> */
    public array $rollbacks = [];

    public function transition(InstanceId $instance, string $event): mixed
    {
        $this->transitions[] = ['instance' => $instance, 'event' => $event];

        return $instance;
    }

    public function rollback(InstanceId $instance, int $targetRevision): mixed
    {
        $this->rollbacks[] = ['instance' => $instance, 'targetRevision' => $targetRevision];

        return $instance;
    }
}
