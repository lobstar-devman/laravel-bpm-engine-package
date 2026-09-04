<?php

namespace App\Mcp\Tools;

use App\Bpm\Contracts\RevisionGateway;
use App\Bpm\ValueObjects\InstanceId;
use App\Domain\Expenses\Services\AutoApprovalDecisionService;
use App\Models\ExpenseReport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Approve an expense report as its assigned manager, running the auto-approval threshold check.')]
class ApproveExpense extends Tool
{
    public function __construct(
        protected AutoApprovalDecisionService $autoApprovalDecisionService,
        protected RevisionGateway $revisionGateway,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'expense_report_id' => ['required', 'string', 'exists:expense_reports,id'],
        ]);

        $expenseReport = ExpenseReport::findOrFail($validated['expense_report_id']);

        if (! $request->user()?->can('approve', $expenseReport)) {
            return Response::error('Permission denied.');
        }

        $autoApprove = $this->autoApprovalDecisionService->evaluate($expenseReport);

        $this->revisionGateway->transition(
            InstanceId::fromInstance($expenseReport->instance),
            $autoApprove ? 'auto_approve' : 'send_to_finance',
        );

        return Response::json([
            'expense_report_id' => $expenseReport->id,
            'auto_approved' => $autoApprove,
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
            'expense_report_id' => $schema->string()->description('The expense report to approve.')->required(),
        ];
    }
}
