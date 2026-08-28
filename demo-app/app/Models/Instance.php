<?php

namespace App\Models;

use Database\Factories\InstanceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The app's model over the package's `instances` table. The package
 * ships no Eloquent models of its own, only migrations, so this is the
 * one place the app couples directly to the package's raw schema.
 */
class Instance extends Model
{
    /** @use HasFactory<InstanceFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'model_revision_id',
        'type',
        'current_state',
    ];
}
