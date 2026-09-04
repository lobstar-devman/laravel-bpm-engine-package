<?php

namespace App\Mcp\Tools;

use App\Enums\ExpenseDisputeState;
use App\Models\ExpenseDispute;
use App\Models\ExpenseReport;
use App\Models\Instance;
use App\Models\ModelDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Open an Expense Dispute case over a rejected expense report.')]
class OpenDispute extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'expense_report_id' => ['required', 'string', 'exists:expense_reports,id'],
            'evidence_summary' => ['required', 'string'],
        ]);

        $expenseReport = ExpenseReport::findOrFail($validated['expense_report_id']);

        // No ExpenseDispute exists yet to authorize against, so this
        // resolves ExpenseDisputePolicy explicitly (an ExpenseReport
        // argument would otherwise resolve ExpenseReportPolicy instead).
        if (! $request->user()?->can('open', [ExpenseDispute::class, $expenseReport])) {
            return Response::error('Permission denied.');
        }

        $dispute = DB::transaction(function () use ($request, $expenseReport, $validated) {
            $revision = ModelDefinition::where('key', 'expense_dispute')
                ->firstOrFail()
                ->revisions()
                ->latest('revision_number')
                ->firstOrFail();

            $caseInstance = Instance::create([
                'model_revision_id' => $revision->id,
                'type' => 'case',
                'current_state' => ExpenseDisputeState::Open->value,
            ]);

            return ExpenseDispute::create([
                'instance_id' => $caseInstance->id,
                'expense_report_id' => $expenseReport->id,
                'opened_by' => $request->user()->id,
                'evidence_summary' => $validated['evidence_summary'],
            ]);
        });

        return Response::json([
            'expense_dispute_id' => $dispute->id,
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
            'expense_report_id' => $schema->string()->description('The rejected expense report being disputed.')->required(),
            'evidence_summary' => $schema->string()->description('A summary of the evidence for the dispute.')->required(),
        ];
    }
}
