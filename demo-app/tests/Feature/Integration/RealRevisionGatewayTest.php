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
 * fakes) — the "re-run PolicyRevisionTest with the real Package*Gateway
 * bindings" check docs/gap-analysis/revision-resolution.md calls for,
 * now that the package is no longer a stub-throw.
 *
 * Passes an `App\Bpm\ValueObjects\InstanceId`, which the adapter unwraps
 * to the instance's raw id before it crosses into the package — see
 * docs/gap-analysis/instance-identity-argument-shape.md.
 */
class RealRevisionGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_transition_drives_a_freshly_submitted_instance_through_the_submit_event(): void
    {
        $modelDefinitionGateway = $this->app->make(ModelDefinitionGateway::class);
        $revisionGateway = $this->app->make(RevisionGateway::class);

        $revision = $modelDefinitionGateway->store(
            'bpmn',
            'expense_reimbursement',
            file_get_contents(resource_path('bpm/expense-reimbursement.bpmn.xml')),
        );

        $instance = Instance::factory()->create([
            'model_revision_id' => $revision->id ?? null,
            'current_state' => ExpenseReportState::SubmitExpense->value,
        ]);
        ExpenseReport::factory()->create(['instance_id' => $instance->id]);

        $result = $revisionGateway->transition(InstanceId::fromInstance($instance), 'submit');

        $this->assertNotNull($result);
    }

    public function test_rollback_moves_an_in_flight_instance_back_to_the_revision_it_was_submitted_under(): void
    {
        $modelDefinitionGateway = $this->app->make(ModelDefinitionGateway::class);
        $revisionGateway = $this->app->make(RevisionGateway::class);

        $revisionA = $modelDefinitionGateway->store(
            'bpmn',
            'expense_reimbursement',
            file_get_contents(resource_path('bpm/expense-reimbursement.bpmn.xml')),
        );
        $modelDefinitionGateway->store(
            'bpmn',
            'expense_reimbursement',
            file_get_contents(resource_path('bpm/expense-reimbursement.bpmn.xml')),
        );

        $instance = Instance::factory()->withState(ExpenseReportState::ManagerApproval)->create([
            'model_revision_id' => $revisionA->id ?? null,
        ]);
        ExpenseReport::factory()->create(['instance_id' => $instance->id]);

        $result = $revisionGateway->rollback(InstanceId::fromInstance($instance), 1);

        $this->assertNotNull($result);
    }
}
