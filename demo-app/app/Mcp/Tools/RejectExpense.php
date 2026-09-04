<?php

namespace App\Mcp\Tools;

use App\Bpm\Contracts\RevisionGateway;
use App\Bpm\ValueObjects\InstanceId;
use App\Enums\ExpenseReportState;
use App\Models\ExpenseReport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Reject an expense report, either as the assigned manager during Manager Approval or as Finance during Finance Review.')]
class RejectExpense extends Tool
{
    public function __construct(protected RevisionGateway $revisionGateway) {}

    /**
     * Handle the tool request.
     *
     * The package's transition() signature carries only (instance,
     * event) with no payload parameter, so `reason` cannot be persisted
     * anywhere via the documented API today — it's accepted here for
     * agent UX but not currently stored.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'expense_report_id' => ['required', 'string', 'exists:expense_reports,id'],
            'reason' => ['required', 'string'],
        ]);

        $expenseReport = ExpenseReport::findOrFail($validated['expense_report_id']);
        $user = $request->user();

        $event = match (true) {
            $expenseReport->currentState === ExpenseReportState::ManagerApproval && $user?->can('reject', $expenseReport) => 'reject',
            $expenseReport->currentState === ExpenseReportState::FinanceReview && $user?->can('financeReject', $expenseReport) => 'finance_reject',
            default => null,
        };

        if ($event === null) {
            return Response::error('Permission denied.');
        }

        $this->revisionGateway->transition(InstanceId::fromInstance($expenseReport->instance), $event);

        return Response::json([
            'expense_report_id' => $expenseReport->id,
            'event' => $event,
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
            'expense_report_id' => $schema->string()->description('The expense report to reject.')->required(),
            'reason' => $schema->string()->description('Why the expense is being rejected.')->required(),
        ];
    }
}
