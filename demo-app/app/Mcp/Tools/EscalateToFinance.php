<?php

namespace App\Mcp\Tools;

use App\Bpm\Contracts\RevisionGateway;
use App\Models\ExpenseReport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Manually escalate a single expense report to Finance Review, ahead of the automatic 5-business-day escalation.')]
class EscalateToFinance extends Tool
{
    public function __construct(protected RevisionGateway $revisionGateway) {}

    /**
     * Handle the tool request.
     *
     * Deliberately single-instance via RevisionGateway::transition(),
     * not routed through BulkTransitionGateway::dispatchBulk() — that
     * batched-by-(event, revision) path is reserved for the scheduled
     * escalation job, and using it for one ad hoc instance would misuse
     * its contract.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'expense_report_id' => ['required', 'string', 'exists:expense_reports,id'],
        ]);

        $expenseReport = ExpenseReport::findOrFail($validated['expense_report_id']);

        if (! $request->user()?->can('escalate', $expenseReport)) {
            return Response::error('Permission denied.');
        }

        $this->revisionGateway->transition($expenseReport->instance, 'escalate_to_finance');

        return Response::json([
            'expense_report_id' => $expenseReport->id,
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
            'expense_report_id' => $schema->string()->description('The expense report to escalate to Finance.')->required(),
        ];
    }
}
