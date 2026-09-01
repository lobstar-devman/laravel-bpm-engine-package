<?php

namespace Tests\Feature\Integration;

use App\Bpm\Contracts\ModelDefinitionGateway;
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
 * Currently red — see docs/gap-analysis/instance-identity-argument-shape.md.
 * `QueueDispatcher::dispatchBulk()` expects each instance's raw id, not
 * the `App\Models\Instance` objects this command actually passes.
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

        $overdueInstance = Instance::factory()->withState('manager_approval')->create([
            'model_revision_id' => $revision->id,
        ]);
        ExpenseReport::factory()->create([
            'instance_id' => $overdueInstance->id,
            'submitted_at' => now()->subDays(15),
        ]);

        $this->artisan('expenses:escalate-overdue')->assertExitCode(0);

        $this->assertNotSame('manager_approval', $overdueInstance->fresh()->current_state);
    }
}
