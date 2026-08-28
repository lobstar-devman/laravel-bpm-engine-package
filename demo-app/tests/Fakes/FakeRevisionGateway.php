<?php

namespace Tests\Fakes;

use App\Bpm\Contracts\RevisionGateway;

class FakeRevisionGateway implements RevisionGateway
{
    /** @var array<int, array{instance: mixed, event: string}> */
    public array $transitions = [];

    /** @var array<int, array{instance: mixed, targetRevision: int}> */
    public array $rollbacks = [];

    public function transition(mixed $instance, string $event): mixed
    {
        $this->transitions[] = ['instance' => $instance, 'event' => $event];

        return $instance;
    }

    public function rollback(mixed $instance, int $targetRevision): mixed
    {
        $this->rollbacks[] = ['instance' => $instance, 'targetRevision' => $targetRevision];

        return $instance;
    }
}
