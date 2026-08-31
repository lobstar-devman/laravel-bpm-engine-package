<?php

namespace Lobstar\BpmEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lobstar\BpmEngine\Models\Concerns\HasUuidPrimaryKey;

/**
 * A single revision of a ModelDefinition's XML — see ADR-003 and
 * docs/arc42/08-crosscutting-concepts.md.
 *
 * @property string $id
 * @property string $model_definition_id
 * @property int $revision_number
 * @property string $xml
 * @property Carbon $created_at
 */
class ModelRevision extends Model
{
    use HasUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $table = 'model_revisions';

    protected $fillable = ['id', 'model_definition_id', 'revision_number', 'xml'];

    /** @return BelongsTo<ModelDefinition, $this> */
    public function modelDefinition(): BelongsTo
    {
        return $this->belongsTo(ModelDefinition::class);
    }
}
