<?php

namespace App\Models;

use Database\Factories\ModelRevisionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The app's model over the package's `model_revisions` table (same
 * one-model-per-package-table pattern as {@see Instance}).
 */
class ModelRevision extends Model
{
    /** @use HasFactory<ModelRevisionFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'model_definition_id',
        'revision_number',
        'xml',
    ];

    public function modelDefinition(): BelongsTo
    {
        return $this->belongsTo(ModelDefinition::class);
    }
}
