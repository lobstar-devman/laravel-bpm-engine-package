<?php

namespace Lobstar\BpmEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lobstar\BpmEngine\Models\Concerns\HasUuidPrimaryKey;

/**
 * A record of a single DMN decision evaluation (inputs, outputs, and
 * which model revision was used), optionally tied to an instance, for
 * auditability — see docs/arc42/08-crosscutting-concepts.md.
 *
 * @property string $id
 * @property string $model_revision_id
 * @property string|null $instance_id
 * @property array<string, mixed> $inputs
 * @property array<string, mixed> $outputs
 * @property Carbon $evaluated_at
 */
class DecisionLog extends Model
{
    use HasUuidPrimaryKey;

    public const CREATED_AT = 'evaluated_at';

    public const UPDATED_AT = null;

    protected $table = 'decision_logs';

    protected $fillable = ['id', 'model_revision_id', 'instance_id', 'inputs', 'outputs'];

    protected $casts = ['inputs' => 'array', 'outputs' => 'array'];

    /** @return BelongsTo<ModelRevision, $this> */
    public function modelRevision(): BelongsTo
    {
        return $this->belongsTo(ModelRevision::class);
    }

    /** @return BelongsTo<Instance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }
}
