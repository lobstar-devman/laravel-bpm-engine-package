<?php

namespace Tests\Feature\Integration;

use App\Bpm\Contracts\ModelDefinitionGateway;
use App\Domain\Expenses\Services\AutoApprovalDecisionService;
use App\Models\DecisionLog;
use App\Models\ExpenseReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract check against the real `lobstar/bpm-engine` bindings (no
 * fakes). Evaluates the real "Auto-Approval Threshold" DMN table
 * (resources/bpm/auto-approval-threshold.dmn.xml) via the real
 * DmnEvaluator, checking outcomes against the rules documented in that
 * XML rather than against package internals.
 *
 * Currently red — see docs/gap-analysis/dmn-evaluator-model-conversion.md.
 * `DmnEvaluator::evaluate()` throws when given the value
 * `ModelRegistry::resolve()` itself returns for a `dmn` key.
 */
class RealDecisionGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(ModelDefinitionGateway::class)->store(
            'dmn',
            'auto_approval_threshold',
            file_get_contents(resource_path('bpm/auto-approval-threshold.dmn.xml')),
        );
    }

    public function test_an_expense_at_or_under_the_threshold_auto_approves(): void
    {
        $expenseReport = ExpenseReport::factory()->create([
            'amount' => 42.50,
            'category' => 'software',
        ]);

        $service = $this->app->make(AutoApprovalDecisionService::class);

        $this->assertTrue($service->evaluate($expenseReport));
    }

    public function test_an_expense_over_the_threshold_does_not_auto_approve(): void
    {
        $expenseReport = ExpenseReport::factory()->create([
            'amount' => 1000,
            'category' => 'software',
        ]);

        $service = $this->app->make(AutoApprovalDecisionService::class);

        $this->assertFalse($service->evaluate($expenseReport));
    }

    public function test_a_travel_expense_never_auto_approves_regardless_of_amount(): void
    {
        $expenseReport = ExpenseReport::factory()->create([
            'amount' => 10,
            'category' => 'travel',
        ]);

        $service = $this->app->make(AutoApprovalDecisionService::class);

        $this->assertFalse($service->evaluate($expenseReport));
    }

    public function test_it_writes_a_decision_log_row_with_a_resolvable_revision_id(): void
    {
        $expenseReport = ExpenseReport::factory()->create([
            'amount' => 42.50,
            'category' => 'software',
        ]);

        $this->app->make(AutoApprovalDecisionService::class)->evaluate($expenseReport);

        $decisionLog = DecisionLog::sole();
        $this->assertNotNull($decisionLog->model_revision_id);
        $this->assertSame($expenseReport->instance_id, $decisionLog->instance_id);
        $this->assertArrayHasKey('auto_approve', $decisionLog->outputs);
    }
}
