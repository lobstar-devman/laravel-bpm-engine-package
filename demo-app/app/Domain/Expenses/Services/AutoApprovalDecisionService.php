<?php

namespace App\Domain\Expenses\Services;

use App\Bpm\Contracts\DecisionGateway;
use App\Models\ExpenseReport;

/**
 * Orchestrates the "Evaluate Auto-Approval Threshold" business-rule
 * step: evaluates the DMN decision by key (DmnEvaluator resolves the
 * current revision internally). `DmnEvaluator::evaluate()` writes its
 * own Decision Log row internally (per the package's ADR-009) — this
 * app must not write a second one; see
 * docs/gap-analysis/dmn-evaluator-model-conversion.md.
 */
class AutoApprovalDecisionService
{
    public function __construct(protected DecisionGateway $decisionGateway) {}

    public function evaluate(ExpenseReport $expenseReport): bool
    {
        $inputs = [
            'amount' => (float) $expenseReport->amount,
            'category' => $expenseReport->category,
        ];

        $outputs = $this->decisionGateway->evaluate('auto_approval_threshold', $inputs);

        return (bool) ($outputs['auto_approve'] ?? false);
    }
}
