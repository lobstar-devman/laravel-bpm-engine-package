<?php

namespace App\Bpm\ValueObjects;

use App\Models\Instance;
use Stringable;

/**
 * Wraps an `App\Models\Instance` row's raw UUID primary key for crossing
 * into `lobstar/bpm-engine`'s RevisionManager/QueueDispatcher, which
 * expect the id string, not the Eloquent model — see
 * docs/gap-analysis/instance-identity-argument-shape.md. Deliberately
 * does not implement Arrayable/JsonSerializable, so it can't be
 * silently treated as an array of keys or JSON-encoded into an object
 * blob the way the raw Eloquent model was.
 */
final readonly class InstanceId implements Stringable
{
    public function __construct(public string $value) {}

    public static function fromInstance(Instance $instance): self
    {
        return new self($instance->id);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
