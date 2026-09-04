<?php

namespace App\Console\Commands;

use App\Bpm\Contracts\BulkTransitionGateway;
use App\Bpm\ValueObjects\InstanceId;
use App\Enums\ExpenseReportState;
use App\Models\ExpenseReport;
use App\Support\BusinessDays;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('expenses:escalate-overdue')]
#[Description('Escalate expense reports still pending Manager Approval after 5 business days')]
class EscalateOverdueExpenseReports extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BulkTransitionGateway $bulkTransitionGateway): int
    {
        $cutoff = BusinessDays::subtract(Carbon::now()->toImmutable(), 5);

        $overdue = ExpenseReport::query()
            ->whereHas('instance', fn ($instances) => $instances->where('current_state', ExpenseReportState::ManagerApproval))
            ->where('submitted_at', '<=', $cutoff)
            ->with('instance')
            ->get();

        // Group by (event, model revision) per Section 6's Runtime View —
        // the event is fixed here, so this collapses to grouping by revision.
        $overdue->groupBy(fn (ExpenseReport $expenseReport) => $expenseReport->instance->model_revision_id)
            ->each(function ($group) use ($bulkTransitionGateway) {
                $bulkTransitionGateway->dispatchBulk(
                    $group->map(fn (ExpenseReport $expenseReport): InstanceId => InstanceId::fromInstance($expenseReport->instance)),
                    'escalate_to_finance',
                );
            });

        $this->info("Escalated {$overdue->count()} overdue expense report(s).");

        return self::SUCCESS;
    }
}
