<?php

namespace Tests\Feature\Integration;

use App\Bpm\Contracts\ModelDefinitionGateway;
use App\Enums\UserRole;
use App\Mcp\Tools\SubmitExpense;
use App\Models\ExpenseReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * Contract check against the real `lobstar/bpm-engine` bindings (no
 * fakes) — see docs/gap-analysis/model-revision-id-resolution.md.
 *
 * `SubmitExpense` (and `OpenDispute`, covered separately) previously
 * derived `model_revision_id` from `ModelDefinitionGateway::resolve()`'s
 * return value. Every other test covering it uses `UsesFakeBpmGateways`,
 * whose fake `resolve()` happens to return a real `App\Models\ModelRevision`
 * (so it has an `->id`) — masking that the real, unstubbed package's
 * `resolve()` returns a parsed process model with no `id` at all, which
 * drove `model_revision_id` to `null` and failed the column's NOT NULL
 * constraint the first time the tool ran against the real gateways (e.g.
 * via the MCP inspector). This test exercises the tool through the real
 * `ModelDefinitionGateway`/`RevisionGateway` bindings so that gap can't
 * reopen silently.
 */
class RealSubmitExpenseGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_an_expense_sets_a_real_model_revision_id(): void
    {
        $this->app->make(ModelDefinitionGateway::class)->store(
            'bpmn',
            'expense_reimbursement',
            file_get_contents(resource_path('bpm/expense-reimbursement.bpmn.xml')),
        );

        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($employee);

        $tool = $this->app->make(SubmitExpense::class);
        $response = $tool->handle(new Request([
            'amount' => 42.50,
            'category' => 'travel',
            'manager_email' => $manager->email,
        ]));

        $this->assertFalse($response->isError());

        $expenseReport = ExpenseReport::sole();
        $this->assertNotNull($expenseReport->instance->model_revision_id);
    }
}
