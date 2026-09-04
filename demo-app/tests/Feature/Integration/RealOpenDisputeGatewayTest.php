<?php

namespace Tests\Feature\Integration;

use App\Bpm\Contracts\ModelDefinitionGateway;
use App\Enums\ExpenseDisputeState;
use App\Enums\ExpenseReportState;
use App\Mcp\Tools\OpenDispute;
use App\Models\ExpenseDispute;
use App\Models\ExpenseReport;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * Contract check against the real `lobstar/bpm-engine` bindings (no
 * fakes) — see docs/gap-analysis/cmmn-ad-hoc-case-plan-unsupported.md.
 *
 * `resources/bpm/expense-dispute.cmmn.xml` was rewritten as a single
 * linear plan per ADR-010 ("CMMN Case Plan Scope"): exactly one
 * designated start (the plan item with no `entryCriterion`) and every
 * other plan item entered via an `entryCriterion`/`sentry`/
 * `planItemOnPart` chain. `ExpenseDisputeState::Open` was corrected to
 * that real start plan item's id (`PlanItem_GatherEvidence`, not the
 * `casePlanModel`'s own id, which was an unverified guess flagged in
 * docs/gap-analysis/bpmn-state-naming-convention.md). `OpenDispute` no
 * longer calls `transition()` at case-creation time: by construction,
 * the start plan item can never have an inbound named transition (it's
 * defined by having no `entryCriterion`), mirroring how `SubmitExpense`
 * reaches its own start task via an unnamed BPMN flow, needing no
 * initiating event either.
 */
class RealOpenDisputeGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_dispute_starts_the_real_case_at_its_designated_start_plan_item(): void
    {
        $this->app->make(ModelDefinitionGateway::class)->store(
            'cmmn',
            'expense_dispute',
            file_get_contents(resource_path('bpm/expense-dispute.cmmn.xml')),
        );

        $employee = User::factory()->create();
        $instance = Instance::factory()->withState(ExpenseReportState::Rejected)->create();
        $expenseReport = ExpenseReport::factory()->create([
            'instance_id' => $instance->id,
            'submitter_id' => $employee->id,
        ]);
        $this->actingAs($employee);

        $tool = $this->app->make(OpenDispute::class);
        $response = $tool->handle(new Request([
            'expense_report_id' => $expenseReport->id,
            'evidence_summary' => 'Receipt was attached in the original submission.',
        ]));

        $this->assertFalse($response->isError());

        $dispute = ExpenseDispute::sole();
        $this->assertNotNull($dispute->instance->model_revision_id);
        $this->assertSame(ExpenseDisputeState::Open->value, $dispute->instance->current_state);
    }
}
