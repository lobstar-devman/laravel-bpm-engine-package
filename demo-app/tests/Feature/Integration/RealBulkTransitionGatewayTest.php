<?php

namespace Tests\Feature\Integration;

use App\Bpm\Contracts\ModelDefinitionGateway;
use App\Enums\ExpenseReportState;
use App\Models\ExpenseReport;
use App\Models\Instance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract check against the real `lobstar/bpm-engine` bindings (no
 * fakes). Runs the real `expenses:escalate-overdue` command, which calls
 * `BulkTransitionGateway::dispatchBulk()` (backed by the real
 * `QueueDispatcher`) exactly as it does in production.
 *
 * The command builds `App\Bpm\ValueObjects\InstanceId` per instance,
 * which the adapter unwraps to the raw id before it crosses into the
 * package — see docs/gap-analysis/instance-identity-argument-shape.md.
 */
class RealBulkTransitionGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_escalating_overdue_reports_dispatches_against_the_real_queue_dispatcher(): void
    {
        $revision = $this->app->make(ModelDefinitionGateway::class)->store(
            'bpmn',
            'expense_reimbursement',
            file_get_contents(resource_path('bpm/expense-reimbursement.bpmn.xml')),
        );

        $overdueInstance = Instance::factory()->withState(ExpenseReportState::ManagerApproval)->create([
            'model_revision_id' => $revision->id,
        ]);
        ExpenseReport::factory()->create([
            'instance_id' => $overdueInstance->id,
            'submitted_at' => now()->subDays(15),
        ]);

        $this->artisan('expenses:escalate-overdue')->assertExitCode(0);

        $this->assertNotSame(ExpenseReportState::ManagerApproval->value, $overdueInstance->fresh()->current_state);
    }
}
