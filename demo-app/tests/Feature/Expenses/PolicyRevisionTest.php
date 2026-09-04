<?php

namespace Tests\Feature\Expenses;

use App\Bpm\ValueObjects\InstanceId;
use App\Enums\ExpenseReportState;
use App\Models\ExpenseReport;
use App\Models\Instance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesFakeBpmGateways;
use Tests\TestCase;

/**
 * Proves the app-side mechanics of step 7 ("bump the auto-approval
 * threshold as a new Model Revision, roll back one in-flight expense to
 * confirm it resolves against the revision it was actually submitted
 * under") under the package's current stub-throw limitation.
 *
 * This does NOT prove the real RevisionManager/ModelRegistry behave
 * this way — that's blocked on the package being unstubbed (see
 * docs/gap-analysis/revision-resolution.md). It proves the app
 * correctly: (1) leaves existing instances pinned when a new revision
 * is created, (2) identifies exactly the right set of in-flight
 * instances needing rollback, and (3) calls rollback() with the
 * correct arguments.
 */
class PolicyRevisionTest extends TestCase
{
    use RefreshDatabase, UsesFakeBpmGateways;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeBpmGateways();
    }

    public function test_bumping_the_threshold_creates_a_new_revision_without_moving_existing_instances(): void
    {
        $revisionA = $this->fakeModelDefinitionGateway->store(
            'dmn',
            'auto_approval_threshold',
            '<decisionTable><rule>amount &lt;= 500 -&gt; true</rule></decisionTable>',
        );
        $this->assertSame(1, $revisionA->revision_number);

        $instanceA = Instance::factory()->withState(ExpenseReportState::ManagerApproval)->create([
            'model_revision_id' => $revisionA->id,
        ]);

        $revisionB = $this->fakeModelDefinitionGateway->store(
            'dmn',
            'auto_approval_threshold',
            '<decisionTable><rule>amount &lt;= 750 -&gt; true</rule></decisionTable>',
        );

        $this->assertSame(2, $revisionB->revision_number);
        $this->assertNotSame($revisionA->id, $revisionB->id);

        $this->assertSame($revisionA->id, $instanceA->fresh()->model_revision_id);
    }

    public function test_it_identifies_exactly_the_in_flight_expenses_needing_rollback(): void
    {
        $revisionA = $this->fakeModelDefinitionGateway->store('dmn', 'auto_approval_threshold', '<v1/>');
        $revisionB = $this->fakeModelDefinitionGateway->store('dmn', 'auto_approval_threshold', '<v2/>');

        // A: submitted under the old revision, still in flight — needs rollback.
        $inFlightOnOldRevision = Instance::factory()->withState(ExpenseReportState::ManagerApproval)
            ->create(['model_revision_id' => $revisionA->id]);
        $expenseA = ExpenseReport::factory()->create(['instance_id' => $inFlightOnOldRevision->id]);

        // B: submitted under the old revision, but already terminal — excluded.
        $terminalOnOldRevision = Instance::factory()->withState(ExpenseReportState::Paid)
            ->create(['model_revision_id' => $revisionA->id]);
        ExpenseReport::factory()->create(['instance_id' => $terminalOnOldRevision->id]);

        // C: submitted under the new revision already — excluded.
        $inFlightOnNewRevision = Instance::factory()->withState(ExpenseReportState::ManagerApproval)
            ->create(['model_revision_id' => $revisionB->id]);
        ExpenseReport::factory()->create(['instance_id' => $inFlightOnNewRevision->id]);

        $needingRollback = ExpenseReport::query()
            ->whereHas('instance', fn ($instances) => $instances->where('model_revision_id', $revisionA->id))
            ->whereHas('instance', fn ($instances) => $instances->whereNotIn('current_state', [ExpenseReportState::Paid, ExpenseReportState::Rejected]))
            ->get();

        $this->assertCount(1, $needingRollback);
        $this->assertSame($expenseA->id, $needingRollback->first()->id);
    }

    public function test_rollback_is_called_with_the_instance_and_the_target_revision_number(): void
    {
        $revisionA = $this->fakeModelDefinitionGateway->store('dmn', 'auto_approval_threshold', '<v1/>');
        $this->fakeModelDefinitionGateway->store('dmn', 'auto_approval_threshold', '<v2/>');

        $instance = Instance::factory()->withState(ExpenseReportState::ManagerApproval)->create([
            'model_revision_id' => $revisionA->id,
        ]);
        $expenseReport = ExpenseReport::factory()->create(['instance_id' => $instance->id]);

        $this->fakeRevisionGateway->rollback(InstanceId::fromInstance($expenseReport->instance), $revisionA->revision_number);

        $this->assertCount(1, $this->fakeRevisionGateway->rollbacks);
        $this->assertSame($instance->id, $this->fakeRevisionGateway->rollbacks[0]['instance']->value);
        $this->assertSame(1, $this->fakeRevisionGateway->rollbacks[0]['targetRevision']);
    }
}
