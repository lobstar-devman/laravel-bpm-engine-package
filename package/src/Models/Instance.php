<?php

namespace Lobstar\BpmEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lobstar\BpmEngine\Models\Concerns\HasUuidPrimaryKey;

/**
 * An active process or case entity being driven through a specific model
 * revision — see docs/arc42/08-crosscutting-concepts.md.
 *
 * @property string $id
 * @property string $model_revision_id
 * @property string $type
 * @property string $current_state
 * @property Carbon $created_at
 */
class Instance extends Model
{
    use HasUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $table = 'instances';

    protected $fillable = ['id', 'model_revision_id', 'type', 'current_state'];

    /** @return BelongsTo<ModelRevision, $this> */
    public function modelRevision(): BelongsTo
    {
        return $this->belongsTo(ModelRevision::class);
    }
}
