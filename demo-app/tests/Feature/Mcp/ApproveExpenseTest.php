<?php

namespace Tests\Feature\Mcp;

use App\Enums\ExpenseReportState;
use App\Enums\UserRole;
use App\Mcp\Tools\ApproveExpense;
use App\Models\ExpenseReport;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Tests\Concerns\UsesFakeBpmGateways;
use Tests\TestCase;

class ApproveExpenseTest extends TestCase
{
    use RefreshDatabase, UsesFakeBpmGateways;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeBpmGateways();
        $this->fakeModelDefinitionGateway->store('dmn', 'auto_approval_threshold', '<definitions/>');
    }

    public function test_the_assigned_manager_can_approve_and_it_auto_approves_under_threshold(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $instance = Instance::factory()->withState(ExpenseReportState::ManagerApproval)->create();
        $expenseReport = ExpenseReport::factory()->create([
            'instance_id' => $instance->id,
            'manager_id' => $manager->id,
        ]);

        $this->fakeDecisionGateway->result = ['auto_approve' => true];
        $this->actingAs($manager);

        $tool = $this->app->make(ApproveExpense::class);

        $response = $tool->handle(new Request(['expense_report_id' => $expenseReport->id]));

        $this->assertFalse($response->isError());
        $this->assertCount(1, $this->fakeRevisionGateway->transitions);
        $this->assertSame('auto_approve', $this->fakeRevisionGateway->transitions[0]['event']);
    }

    public function test_it_sends_to_finance_when_over_threshold(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $instance = Instance::factory()->withState(ExpenseReportState::ManagerApproval)->create();
        $expenseReport = ExpenseReport::factory()->create([
            'instance_id' => $instance->id,
            'manager_id' => $manager->id,
        ]);

        $this->fakeDecisionGateway->result = ['auto_approve' => false];
        $this->actingAs($manager);

        $tool = $this->app->make(ApproveExpense::class);
        $tool->handle(new Request(['expense_report_id' => $expenseReport->id]));

        $this->assertSame('send_to_finance', $this->fakeRevisionGateway->transitions[0]['event']);
    }

    public function test_a_different_manager_cannot_approve(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $otherManager = User::factory()->create(['role' => UserRole::Manager]);
        $instance = Instance::factory()->withState(ExpenseReportState::ManagerApproval)->create();
        $expenseReport = ExpenseReport::factory()->create([
            'instance_id' => $instance->id,
            'manager_id' => $manager->id,
        ]);

        $this->actingAs($otherManager);

        $tool = $this->app->make(ApproveExpense::class);
        $response = $tool->handle(new Request(['expense_report_id' => $expenseReport->id]));

        $this->assertTrue($response->isError());
        $this->assertCount(0, $this->fakeRevisionGateway->transitions);
    }
}
