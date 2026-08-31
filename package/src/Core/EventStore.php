<?php

namespace Lobstar\BpmEngine\Core;

use Illuminate\Support\Str;
use Lobstar\BpmEngine\Models\Instance;
use Lobstar\BpmEngine\Models\TransitionEvent;

/**
 * Persists the event-sourced log of state transitions. One write per
 * transition event — see ADR-003 and docs/arc42/08-crosscutting-concepts.md.
 */
class EventStore
{
    public function append(mixed $instance, string $eventType, array $payload = []): void
    {
        TransitionEvent::create([
            'id' => (string) Str::uuid(),
            'instance_id' => $this->instanceId($instance),
            'event_type' => $eventType,
            'payload' => $payload,
        ]);
    }

    public function history(mixed $instance): array
    {
        return TransitionEvent::where('instance_id', $this->instanceId($instance))
            ->orderBy('occurred_at')
            ->get()
            ->all();
    }

    private function instanceId(mixed $instance): string
    {
        return $instance instanceof Instance ? $instance->id : (string) $instance;
    }
}
