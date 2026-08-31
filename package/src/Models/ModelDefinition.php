<?php

namespace Lobstar\BpmEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Lobstar\BpmEngine\Models\Concerns\HasUuidPrimaryKey;

/**
 * A BPMN/CMMN/DMN model definition — see docs/arc42/08-crosscutting-concepts.md.
 *
 * @property string $id
 * @property string $standard
 * @property string $key
 * @property string $name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ModelDefinition extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'model_definitions';

    protected $fillable = ['id', 'standard', 'key', 'name'];

    /** @return HasMany<ModelRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ModelRevision::class);
    }
}
