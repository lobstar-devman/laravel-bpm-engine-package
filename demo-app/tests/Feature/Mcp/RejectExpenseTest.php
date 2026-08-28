<?php

namespace Tests\Feature\Mcp;

use App\Enums\UserRole;
use App\Mcp\Tools\RejectExpense;
use App\Models\ExpenseReport;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Tests\Concerns\UsesFakeBpmGateways;
use Tests\TestCase;

class RejectExpenseTest extends TestCase
{
    use RefreshDatabase, UsesFakeBpmGateways;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeBpmGateways();
    }

    public function test_the_assigned_manager_can_reject_during_manager_approval(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $instance = Instance::factory()->withState('manager_approval')->create();
        $expenseReport = ExpenseReport::factory()->create([
            'instance_id' => $instance->id,
            'manager_id' => $manager->id,
        ]);

        $this->actingAs($manager);

        $tool = $this->app->make(RejectExpense::class);
        $response = $tool->handle(new Request([
            'expense_report_id' => $expenseReport->id,
            'reason' => 'Missing receipt',
        ]));

        $this->assertFalse($response->isError());
        $this->assertSame('reject', $this->fakeRevisionGateway->transitions[0]['event']);
    }

    public function test_finance_can_reject_during_finance_review(): void
    {
        $finance = User::factory()->create(['role' => UserRole::Finance]);
        $instance = Instance::factory()->withState('finance_review')->create();
        $expenseReport = ExpenseReport::factory()->create(['instance_id' => $instance->id]);

        $this->actingAs($finance);

        $tool = $this->app->make(RejectExpense::class);
        $response = $tool->handle(new Request([
            'expense_report_id' => $expenseReport->id,
            'reason' => 'Policy violation',
        ]));

        $this->assertFalse($response->isError());
        $this->assertSame('finance_reject', $this->fakeRevisionGateway->transitions[0]['event']);
    }

    public function test_a_manager_cannot_reject_during_finance_review(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $instance = Instance::factory()->withState('finance_review')->create();
        $expenseReport = ExpenseReport::factory()->create([
            'instance_id' => $instance->id,
            'manager_id' => $manager->id,
        ]);

        $this->actingAs($manager);

        $tool = $this->app->make(RejectExpense::class);
        $response = $tool->handle(new Request([
            'expense_report_id' => $expenseReport->id,
            'reason' => 'Trying anyway',
        ]));

        $this->assertTrue($response->isError());
        $this->assertCount(0, $this->fakeRevisionGateway->transitions);
    }
}
