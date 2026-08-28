<?php

namespace Tests\Feature\Mcp;

use App\Enums\UserRole;
use App\Mcp\Tools\OpenDispute;
use App\Models\ExpenseDispute;
use App\Models\ExpenseReport;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Tests\Concerns\UsesFakeBpmGateways;
use Tests\TestCase;

class OpenDisputeTest extends TestCase
{
    use RefreshDatabase, UsesFakeBpmGateways;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeBpmGateways();
        $this->fakeModelDefinitionGateway->store('cmmn', 'expense_dispute', '<definitions/>');
    }

    public function test_the_submitter_can_open_a_dispute_on_a_rejected_expense(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $instance = Instance::factory()->withState('rejected')->create();
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
        $this->assertSame($expenseReport->id, $dispute->expense_report_id);
        $this->assertSame($employee->id, $dispute->opened_by);
        $this->assertSame('case', $dispute->instance->type);
        $this->assertSame('open_dispute', $this->fakeRevisionGateway->transitions[0]['event']);
    }

    public function test_a_dispute_cannot_be_opened_on_a_non_rejected_expense(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $instance = Instance::factory()->withState('paid')->create();
        $expenseReport = ExpenseReport::factory()->create([
            'instance_id' => $instance->id,
            'submitter_id' => $employee->id,
        ]);

        $this->actingAs($employee);

        $tool = $this->app->make(OpenDispute::class);
        $response = $tool->handle(new Request([
            'expense_report_id' => $expenseReport->id,
            'evidence_summary' => 'N/A',
        ]));

        $this->assertTrue($response->isError());
        $this->assertSame(0, ExpenseDispute::count());
    }

    public function test_someone_other_than_the_submitter_cannot_open_a_dispute(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $otherEmployee = User::factory()->create(['role' => UserRole::Employee]);
        $instance = Instance::factory()->withState('rejected')->create();
        $expenseReport = ExpenseReport::factory()->create([
            'instance_id' => $instance->id,
            'submitter_id' => $employee->id,
        ]);

        $this->actingAs($otherEmployee);

        $tool = $this->app->make(OpenDispute::class);
        $response = $tool->handle(new Request([
            'expense_report_id' => $expenseReport->id,
            'evidence_summary' => 'N/A',
        ]));

        $this->assertTrue($response->isError());
    }
}
