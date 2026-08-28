<?php

namespace Tests\Fakes;

use App\Bpm\Contracts\BulkTransitionGateway;

class FakeBulkTransitionGateway implements BulkTransitionGateway
{
    /** @var array<int, array{instances: array<int, mixed>, event: string}> */
    public array $dispatches = [];

    public function dispatchBulk(iterable $instances, string $event): void
    {
        $this->dispatches[] = [
            'instances' => is_array($instances) ? $instances : iterator_to_array($instances),
            'event' => $event,
        ];
    }
}
