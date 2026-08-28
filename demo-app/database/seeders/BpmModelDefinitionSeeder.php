<?php

namespace Database\Seeders;

use App\Bpm\Contracts\ModelDefinitionGateway;
use Illuminate\Database\Seeder;

/**
 * Loads the Expense Reimbursement BPMN, Auto-Approval Threshold DMN, and
 * Expense Dispute CMMN definitions into the model registry.
 *
 * This cannot run to completion against the real package yet —
 * `ModelRegistry::store()` is currently a stub that throws — so it's
 * exercised in tests against the fake gateway instead (see
 * tests/Feature/Expenses/BpmModelDefinitionSeederTest.php).
 */
class BpmModelDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(ModelDefinitionGateway $modelDefinitionGateway): void
    {
        $modelDefinitionGateway->store(
            'bpmn',
            'expense_reimbursement',
            file_get_contents(resource_path('bpm/expense-reimbursement.bpmn.xml')),
        );

        $modelDefinitionGateway->store(
            'dmn',
            'auto_approval_threshold',
            file_get_contents(resource_path('bpm/auto-approval-threshold.dmn.xml')),
        );

        $modelDefinitionGateway->store(
            'cmmn',
            'expense_dispute',
            file_get_contents(resource_path('bpm/expense-dispute.cmmn.xml')),
        );
    }
}
