<?php

namespace Tests\Feature\Integration;

use App\Bpm\Contracts\ModelDefinitionGateway;
use App\Enums\ExpenseReportState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract check against the real `lobstar/bpm-engine` bindings (no
 * fakes) — see docs/gap-analysis/bpmn-state-naming-convention.md and
 * ADR-012 ("BPMN Vocabulary Hash"). `BpmnProcessModel::vocabulary()` is
 * the package's answer to that gap doc's open question of how an app is
 * meant to discover valid state/event ids instead of guessing: it
 * exposes the model's real node ids and sequence-flow names directly,
 * so App\Enums\ExpenseReportState and every hardcoded event-name literal
 * (in the MCP tools and EscalateOverdueExpenseReports) can be checked
 * against the authored XML instead of only being exercised incidentally
 * by whichever transitions happen to run in other tests.
 */
class RealBpmnVocabularyTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_HASH = 'sha256:e859c76db4084ed4ea578a31aa8458a9d643c86e28d7fede149ef78887b59467';

    public function test_expense_report_state_enum_values_are_real_bpmn_node_ids(): void
    {
        $vocabulary = $this->resolveVocabulary();

        foreach (ExpenseReportState::cases() as $state) {
            $this->assertContains(
                $state->value,
                $vocabulary->nodeIds,
                "ExpenseReportState::{$state->name} ({$state->value}) is not a node id in the current BPMN model.",
            );
        }

        $eventNames = ['submit', 'escalate_to_finance', 'auto_approve', 'send_to_finance', 'reject', 'finance_reject'];

        foreach ($eventNames as $eventName) {
            $this->assertContains(
                $eventName,
                $vocabulary->flowNames,
                "Event name [{$eventName}], hardcoded in an MCP tool or console command, is not a sequence-flow name in the current BPMN model.",
            );
        }
    }

    public function test_bpmn_vocabulary_hash_matches_the_committed_baseline(): void
    {
        $this->assertSame(
            self::EXPECTED_HASH,
            $this->resolveVocabulary()->hash(),
            "The BPMN model's vocabulary (node ids / sequence-flow names) has changed. "
            ."Update App\Enums\ExpenseReportState and every hardcoded event-name literal to match, "
            .'then update self::EXPECTED_HASH in this test to the new hash.',
        );
    }

    private function resolveVocabulary(): mixed
    {
        $modelDefinitionGateway = $this->app->make(ModelDefinitionGateway::class);

        $modelDefinitionGateway->store(
            'bpmn',
            'expense_reimbursement',
            file_get_contents(resource_path('bpm/expense-reimbursement.bpmn.xml')),
        );

        return $modelDefinitionGateway->resolve('expense_reimbursement')->vocabulary();
    }
}
