<?php

namespace Tests\Feature\Console;

use App\Enums\ExpenseReportState;
use App\Models\ExpenseReport;
use App\Models\Instance;
use App\Models\ModelRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesFakeBpmGateways;
use Tests\TestCase;

class EscalateOverdueExpenseReportsTest extends TestCase
{
    use RefreshDatabase, UsesFakeBpmGateways;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeBpmGateways();
    }

    public function test_it_groups_overdue_reports_by_model_revision_and_dispatches_one_batch_per_group(): void
    {
        $revisionA = ModelRevision::factory()->create();
        $revisionB = ModelRevision::factory()->create();

        $overdueInstanceA = Instance::factory()->withState(ExpenseReportState::ManagerApproval)
            ->create(['model_revision_id' => $revisionA->id]);
        $overdueInstanceB = Instance::factory()->withState(ExpenseReportState::ManagerApproval)
            ->create(['model_revision_id' => $revisionB->id]);
        $notOverdueInstance = Instance::factory()->withState(ExpenseReportState::ManagerApproval)
            ->create(['model_revision_id' => $revisionA->id]);
        $terminalInstance = Instance::factory()->withState(ExpenseReportState::Paid)
            ->create(['model_revision_id' => $revisionA->id]);

        $overdueA = ExpenseReport::factory()->create([
            'instance_id' => $overdueInstanceA->id,
            'submitted_at' => now()->subDays(15),
        ]);
        $overdueB = ExpenseReport::factory()->create([
            'instance_id' => $overdueInstanceB->id,
            'submitted_at' => now()->subDays(15),
        ]);
        ExpenseReport::factory()->create([
            'instance_id' => $notOverdueInstance->id,
            'submitted_at' => now()->subDay(),
        ]);
        ExpenseReport::factory()->create([
            'instance_id' => $terminalInstance->id,
            'submitted_at' => now()->subDays(20),
        ]);

        $this->artisan('expenses:escalate-overdue')->assertExitCode(0);

        $dispatches = $this->fakeBulkTransitionGateway->dispatches;
        $this->assertCount(2, $dispatches, 'expected one dispatchBulk call per distinct model revision');

        foreach ($dispatches as $dispatch) {
            $this->assertSame('escalate_to_finance', $dispatch['event']);
            $this->assertCount(1, $dispatch['instances']);
        }

        $dispatchedInstanceIds = collect($dispatches)
            ->flatMap(fn (array $dispatch) => collect($dispatch['instances'])->pluck('value'))
            ->sort()
            ->values();

        $this->assertSame(
            collect([$overdueInstanceA->id, $overdueInstanceB->id])->sort()->values()->all(),
            $dispatchedInstanceIds->all(),
        );
    }

    public function test_it_dispatches_nothing_when_no_reports_are_overdue(): void
    {
        $instance = Instance::factory()->withState(ExpenseReportState::ManagerApproval)->create();

        ExpenseReport::factory()->create([
            'instance_id' => $instance->id,
            'submitted_at' => now()->subDay(),
        ]);

        $this->artisan('expenses:escalate-overdue')->assertExitCode(0);

        $this->assertCount(0, $this->fakeBulkTransitionGateway->dispatches);
    }
}
