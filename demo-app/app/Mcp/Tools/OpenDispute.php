<?php

namespace App\Mcp\Tools;

use App\Bpm\Contracts\ModelDefinitionGateway;
use App\Bpm\Contracts\RevisionGateway;
use App\Bpm\Support\RevisionId;
use App\Bpm\ValueObjects\InstanceId;
use App\Enums\ExpenseDisputeState;
use App\Models\ExpenseDispute;
use App\Models\ExpenseReport;
use App\Models\Instance;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Open an ad hoc Expense Dispute case over a rejected expense report.')]
class OpenDispute extends Tool
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
            $revision = $this->modelDefinitionGateway->resolve('expense_dispute');

            $caseInstance = Instance::create([
                'model_revision_id' => RevisionId::from($revision),
                'type' => 'case',
                'current_state' => ExpenseDisputeState::Open->value,
            ]);

            $dispute = ExpenseDispute::create([
                'instance_id' => $caseInstance->id,
                'expense_report_id' => $expenseReport->id,
                'opened_by' => $request->user()->id,
                'evidence_summary' => $validated['evidence_summary'],
            ]);

            $this->revisionGateway->transition(InstanceId::fromInstance($caseInstance), 'open_dispute');

            return $dispute;
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
