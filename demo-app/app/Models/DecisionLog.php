<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * The app's model over the package's `decision_logs` table (same
 * one-model-per-package-table pattern as {@see Instance}).
 */
class DecisionLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'inputs' => 'array',
        'outputs' => 'array',
    ];

    protected $fillable = [
        'id',
        'model_revision_id',
        'instance_id',
        'inputs',
        'outputs',
    ];
}
