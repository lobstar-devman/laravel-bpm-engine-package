<?php

namespace App\Domain\Expenses\Services;

use App\Bpm\Contracts\DecisionGateway;
use App\Bpm\Contracts\ModelDefinitionGateway;
use App\Bpm\Support\RevisionId;
use App\Models\DecisionLog;
use App\Models\ExpenseReport;

/**
 * Orchestrates the "Evaluate Auto-Approval Threshold" business-rule
 * step: resolves the current DMN revision, evaluates it, and writes the
 * Decision Log row. Called from the app rather than left to
 * BpmnInterpreter to invoke internally — the docs list
 * DmnEvaluator::evaluate() as separate public API and omit
 * BpmnInterpreter/CmmnInterpreter from the "build against this" list.
 * This is an assumption to re-verify once the package is unstubbed.
 */
class AutoApprovalDecisionService
{
    public function __construct(
        protected ModelDefinitionGateway $modelDefinitionGateway,
        protected DecisionGateway $decisionGateway,
    ) {}

    public function evaluate(ExpenseReport $expenseReport): bool
    {
        $revision = $this->modelDefinitionGateway->resolve('auto_approval_threshold');

        $inputs = [
            'amount' => (float) $expenseReport->amount,
            'category' => $expenseReport->category,
        ];

        $outputs = $this->decisionGateway->evaluate($revision, $inputs);

        DecisionLog::create([
            'model_revision_id' => RevisionId::from($revision),
            'instance_id' => $expenseReport->instance_id,
            'inputs' => $inputs,
            'outputs' => $outputs,
        ]);

        return (bool) ($outputs['auto_approve'] ?? false);
    }
}
