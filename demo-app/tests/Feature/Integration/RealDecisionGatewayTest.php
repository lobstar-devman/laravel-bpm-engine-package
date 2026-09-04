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
 * See docs/gap-analysis/dmn-evaluator-model-conversion.md for the full
 * history: the original crash (given `ModelRegistry::resolve()`'s
 * output for a `dmn` key) and a since-fixed DMN-authoring bug (this
 * app's `<output>` element had no `name` attribute, only `label`) are
 * both resolved. `DmnEvaluator::evaluate()` writes its own Decision Log
 * row internally (per the package's ADR-009) with `instance_id` always
 * `null` — there is currently no documented way to correlate a decision
 * to a specific instance.
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

    public function test_the_package_writes_its_own_decision_log_row(): void
    {
        $expenseReport = ExpenseReport::factory()->create([
            'amount' => 42.50,
            'category' => 'software',
        ]);

        $this->app->make(AutoApprovalDecisionService::class)->evaluate($expenseReport);

        $decisionLog = DecisionLog::sole();
        $this->assertNotNull($decisionLog->model_revision_id);
        $this->assertNull($decisionLog->instance_id, 'DmnEvaluator hardcodes instance_id to null — see docs/gap-analysis/dmn-evaluator-model-conversion.md.');
        $this->assertSame(['auto_approve' => true], $decisionLog->outputs);
    }
}
