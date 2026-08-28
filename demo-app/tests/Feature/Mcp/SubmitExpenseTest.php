<?php

namespace Tests\Feature\Mcp;

use App\Enums\UserRole;
use App\Mcp\Tools\SubmitExpense;
use App\Models\ExpenseReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Tests\Concerns\UsesFakeBpmGateways;
use Tests\TestCase;

class SubmitExpenseTest extends TestCase
{
    use RefreshDatabase, UsesFakeBpmGateways;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeBpmGateways();
        $this->fakeModelDefinitionGateway->store('bpmn', 'expense_reimbursement', '<definitions/>');
    }

    public function test_an_employee_can_submit_an_expense(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($employee);

        $tool = $this->app->make(SubmitExpense::class);

        $response = $tool->handle(new Request([
            'amount' => 123.45,
            'category' => 'travel',
            'manager_id' => $manager->id,
        ]));

        $this->assertFalse($response->isError());

        $expenseReport = ExpenseReport::sole();
        $this->assertSame($employee->id, $expenseReport->submitter_id);
        $this->assertSame($manager->id, $expenseReport->manager_id);
        $this->assertSame('123.45', (string) $expenseReport->amount);

        $this->assertCount(1, $this->fakeRevisionGateway->transitions);
        $this->assertSame('submit', $this->fakeRevisionGateway->transitions[0]['event']);
    }

    public function test_a_non_employee_cannot_submit_an_expense(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($manager);

        $tool = $this->app->make(SubmitExpense::class);

        $response = $tool->handle(new Request([
            'amount' => 50,
            'category' => 'meals',
            'manager_id' => $manager->id,
        ]));

        $this->assertTrue($response->isError());
        $this->assertSame(0, ExpenseReport::count());
        $this->assertCount(0, $this->fakeRevisionGateway->transitions);
    }
}
