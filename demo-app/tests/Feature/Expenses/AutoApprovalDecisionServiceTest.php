<?php

namespace Tests\Feature\Expenses;

use App\Domain\Expenses\Services\AutoApprovalDecisionService;
use App\Models\DecisionLog;
use App\Models\ExpenseReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesFakeBpmGateways;
use Tests\TestCase;

class AutoApprovalDecisionServiceTest extends TestCase
{
    use RefreshDatabase, UsesFakeBpmGateways;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeBpmGateways();
    }

    public function test_it_writes_a_decision_log_and_returns_the_auto_approve_flag(): void
    {
        $revision = $this->fakeModelDefinitionGateway->store('dmn', 'auto_approval_threshold', '<definitions/>');
        $this->fakeDecisionGateway->result = ['auto_approve' => true];

        $expenseReport = ExpenseReport::factory()->create([
            'amount' => 42.50,
            'category' => 'software',
        ]);

        $service = $this->app->make(AutoApprovalDecisionService::class);

        $result = $service->evaluate($expenseReport);

        $this->assertTrue($result);

        $this->assertCount(1, $this->fakeDecisionGateway->calls);
        $this->assertSame(42.50, $this->fakeDecisionGateway->calls[0]['inputData']['amount']);
        $this->assertSame('software', $this->fakeDecisionGateway->calls[0]['inputData']['category']);

        $decisionLog = DecisionLog::sole();
        $this->assertSame($revision->id, $decisionLog->model_revision_id);
        $this->assertSame($expenseReport->instance_id, $decisionLog->instance_id);
        $this->assertSame(['auto_approve' => true], $decisionLog->outputs);
    }

    public function test_it_returns_false_when_the_decision_omits_auto_approve(): void
    {
        $this->fakeModelDefinitionGateway->store('dmn', 'auto_approval_threshold', '<definitions/>');
        $this->fakeDecisionGateway->result = ['auto_approve' => false];

        $expenseReport = ExpenseReport::factory()->create();

        $service = $this->app->make(AutoApprovalDecisionService::class);

        $this->assertFalse($service->evaluate($expenseReport));
    }
}
