<?php

namespace App\Models;

use App\Enums\ExpenseReportState;
use Database\Factories\ExpenseReportFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExpenseReport extends Model
{
    /** @use HasFactory<ExpenseReportFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'instance_id',
        'submitter_id',
        'manager_id',
        'amount',
        'category',
        'submitted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'submitted_at' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(ExpenseDispute::class);
    }

    /**
     * Proxies the package's own `instances.current_state` rather than
     * duplicating a status column here that could drift out of sync.
     */
    protected function currentState(): Attribute
    {
        return Attribute::get(fn () => ExpenseReportState::from($this->instance->current_state));
    }
}
