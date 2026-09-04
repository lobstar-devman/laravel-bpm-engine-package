<?php

namespace Tests\Feature\Integration;

use App\Bpm\Contracts\ModelDefinitionGateway;
use App\Bpm\Contracts\RevisionGateway;
use App\Bpm\ValueObjects\InstanceId;
use App\Enums\ExpenseReportState;
use App\Models\ExpenseReport;
use App\Models\Instance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract check against the real `lobstar/bpm-engine` bindings (no
 * fakes) — see docs/gap-analysis/bpmn-gateway-unsupported.md.
 *
 * `expense-reimbursement.bpmn.xml` used to route the manager/finance
 * decision points through `exclusiveGateway` nodes. ADR-011 ("BPMN
 * Process Scope") documents that the real `BpmnParser`/`BpmnInterpreter`
 * support only startEvent/task-family/endEvent nodes — no gateways at
 * all — so every event that had to pass through one
 * (`reject`, `auto_approve`, `send_to_finance`, `finance_approve`,
 * `finance_reject`) failed with `Unknown BPMN node [...]` the first time
 * it was exercised against the real package. Every prior test covering
 * these tools used `UsesFakeBpmGateways`, which never parses the XML at
 * all, so this went uncaught. The XML was flattened: each gateway's
 * outgoing edges now hang directly off the preceding task as named
 * sequence flows, matched by event name exactly as the interpreter
 * already does for `escalate_to_finance` — no business behavior changed,
 * only the node/flow shape.
 */
class RealBpmnGatewayFlatteningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: RevisionGateway, 1: Instance}
     */
    private function makeInstanceAt(ExpenseReportState $state): array
    {
        $revision = $this->app->make(ModelDefinitionGateway::class)->store(
            'bpmn',
            'expense_reimbursement',
            file_get_contents(resource_path('bpm/expense-reimbursement.bpmn.xml')),
        );

        $instance = Instance::factory()->withState($state)->create([
            'model_revision_id' => $revision->id ?? null,
        ]);
        ExpenseReport::factory()->create(['instance_id' => $instance->id]);

        return [$this->app->make(RevisionGateway::class), $instance];
    }

    public function test_reject_moves_a_report_under_manager_review_to_rejected(): void
    {
        [$revisionGateway, $instance] = $this->makeInstanceAt(ExpenseReportState::ManagerApproval);

        $revisionGateway->transition(InstanceId::fromInstance($instance), 'reject');

        $this->assertSame(ExpenseReportState::Rejected->value, $instance->fresh()->current_state);
    }

    public function test_auto_approve_moves_a_report_under_manager_review_to_paid(): void
    {
        [$revisionGateway, $instance] = $this->makeInstanceAt(ExpenseReportState::ManagerApproval);

        $revisionGateway->transition(InstanceId::fromInstance($instance), 'auto_approve');

        $this->assertSame(ExpenseReportState::Paid->value, $instance->fresh()->current_state);
    }

    public function test_send_to_finance_moves_a_report_under_manager_review_to_finance_review(): void
    {
        [$revisionGateway, $instance] = $this->makeInstanceAt(ExpenseReportState::ManagerApproval);

        $revisionGateway->transition(InstanceId::fromInstance($instance), 'send_to_finance');

        $this->assertSame(ExpenseReportState::FinanceReview->value, $instance->fresh()->current_state);
    }

    public function test_finance_approve_moves_a_report_under_finance_review_to_paid(): void
    {
        [$revisionGateway, $instance] = $this->makeInstanceAt(ExpenseReportState::FinanceReview);

        $revisionGateway->transition(InstanceId::fromInstance($instance), 'finance_approve');

        $this->assertSame(ExpenseReportState::Paid->value, $instance->fresh()->current_state);
    }

    public function test_finance_reject_moves_a_report_under_finance_review_to_rejected(): void
    {
        [$revisionGateway, $instance] = $this->makeInstanceAt(ExpenseReportState::FinanceReview);

        $revisionGateway->transition(InstanceId::fromInstance($instance), 'finance_reject');

        $this->assertSame(ExpenseReportState::Rejected->value, $instance->fresh()->current_state);
    }
}
