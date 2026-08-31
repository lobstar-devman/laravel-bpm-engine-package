<?php

namespace Lobstar\BpmEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lobstar\BpmEngine\Models\Concerns\HasUuidPrimaryKey;

/**
 * An event-sourced log entry for a single state transition — see ADR-003
 * and docs/arc42/08-crosscutting-concepts.md.
 *
 * @property string $id
 * @property string $instance_id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property Carbon $occurred_at
 */
class TransitionEvent extends Model
{
    use HasUuidPrimaryKey;

    public const CREATED_AT = 'occurred_at';

    public const UPDATED_AT = null;

    protected $table = 'transition_events';

    protected $fillable = ['id', 'instance_id', 'event_type', 'payload'];

    protected $casts = ['payload' => 'array'];

    /** Microsecond precision so occurred_at alone orders same-second events correctly. */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @return BelongsTo<Instance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }
}
