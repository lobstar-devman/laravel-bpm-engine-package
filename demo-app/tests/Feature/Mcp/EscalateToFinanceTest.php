<?php

namespace Tests\Feature\Mcp;

use App\Enums\UserRole;
use App\Mcp\Tools\EscalateToFinance;
use App\Models\ExpenseReport;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Tests\Concerns\UsesFakeBpmGateways;
use Tests\TestCase;

class EscalateToFinanceTest extends TestCase
{
    use RefreshDatabase, UsesFakeBpmGateways;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeBpmGateways();
    }

    public function test_any_finance_user_can_escalate(): void
    {
        $finance = User::factory()->create(['role' => UserRole::Finance]);
        $instance = Instance::factory()->withState('manager_approval')->create();
        $expenseReport = ExpenseReport::factory()->create(['instance_id' => $instance->id]);

        $this->actingAs($finance);

        $tool = $this->app->make(EscalateToFinance::class);
        $response = $tool->handle(new Request(['expense_report_id' => $expenseReport->id]));

        $this->assertFalse($response->isError());
        $this->assertSame('escalate_to_finance', $this->fakeRevisionGateway->transitions[0]['event']);
    }

    public function test_an_employee_cannot_escalate(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $instance = Instance::factory()->withState('manager_approval')->create();
        $expenseReport = ExpenseReport::factory()->create(['instance_id' => $instance->id]);

        $this->actingAs($employee);

        $tool = $this->app->make(EscalateToFinance::class);
        $response = $tool->handle(new Request(['expense_report_id' => $expenseReport->id]));

        $this->assertTrue($response->isError());
        $this->assertCount(0, $this->fakeRevisionGateway->transitions);
    }
}
