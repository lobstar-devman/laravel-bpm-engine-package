<?php

namespace App\Mcp\Tools;

use App\Bpm\Contracts\ModelDefinitionGateway;
use App\Bpm\Contracts\RevisionGateway;
use App\Bpm\Support\RevisionId;
use App\Models\ExpenseReport;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Submit a new expense report for reimbursement, entering the Expense Reimbursement process.')]
class SubmitExpense extends Tool
{
    public function __construct(
        protected ModelDefinitionGateway $modelDefinitionGateway,
        protected RevisionGateway $revisionGateway,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        if (! $request->user()?->can('submit', ExpenseReport::class)) {
            return Response::error('Permission denied.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['required', 'string'],
            'manager_email' => ['required', 'string', 'exists:users,email'],
        ]);

        $expenseReport = DB::transaction(function () use ($request, $validated) {
            $manager = User::where('email', $validated['manager_email'])->firstOrFail();

            $revision = $this->modelDefinitionGateway->resolve('expense_reimbursement');

            $instance = Instance::create([
                'model_revision_id' => RevisionId::from($revision),
                'type' => 'process',
                'current_state' => 'submitted',
            ]);

            $expenseReport = ExpenseReport::create([
                'instance_id' => $instance->id,
                'submitter_id' => $request->user()->id,
                'manager_id' => $manager->id,
                'amount' => $validated['amount'],
                'category' => $validated['category'],
                'submitted_at' => now(),
            ]);

            $this->revisionGateway->transition($instance, 'submit');

            return $expenseReport;
        });

        return Response::json([
            'expense_report_id' => $expenseReport->id,
            'state' => $expenseReport->currentState,
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'amount' => $schema->number()->description('The expense amount.')->required(),
            'category' => $schema->string()->description('The expense category, e.g. travel, meals, supplies, software.')->required(),
            'manager_email' => $schema->string()->description('The email of the user who must approve this expense.')->required(),
        ];
    }
}
