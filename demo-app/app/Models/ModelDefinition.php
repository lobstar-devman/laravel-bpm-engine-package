<?php

namespace App\Models;

use Database\Factories\ModelDefinitionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The app's model over the package's `model_definitions` table (same
 * one-model-per-package-table pattern as {@see Instance}). Needed so
 * tests/factories can create FK-satisfying rows while the package's own
 * `ModelRegistry::store()` is stubbed.
 */
class ModelDefinition extends Model
{
    /** @use HasFactory<ModelDefinitionFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'standard',
        'key',
        'name',
    ];

    public function revisions(): HasMany
    {
        return $this->hasMany(ModelRevision::class);
    }
}
