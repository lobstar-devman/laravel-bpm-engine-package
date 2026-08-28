<?php

namespace App\Models;

use Database\Factories\ExpenseDisputeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseDispute extends Model
{
    /** @use HasFactory<ExpenseDisputeFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'instance_id',
        'expense_report_id',
        'opened_by',
        'investigator_id',
        'finance_director_id',
        'evidence_summary',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function expenseReport(): BelongsTo
    {
        return $this->belongsTo(ExpenseReport::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function investigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigator_id');
    }

    public function financeDirector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_director_id');
    }
}
