<?php

namespace Lobstar\BpmEngine\Core;

/**
 * Persists the event-sourced log of state transitions. One write per
 * transition event — see ADR-003 and docs/arc42/08-crosscutting-concepts.md.
 */
class EventStore
{
    public function append(mixed $instance, string $eventType, array $payload = []): void
    {
        throw new \RuntimeException('Not implemented yet.');
    }

    public function history(mixed $instance): array
    {
        throw new \RuntimeException('Not implemented yet.');
    }
}
