<?php

namespace App\Policies;

use App\Enums\ExpenseReportState;
use App\Enums\UserRole;
use App\Models\ExpenseDispute;
use App\Models\ExpenseReport;
use App\Models\User;

class ExpenseDisputePolicy
{
    public function open(User $user, ExpenseReport $expenseReport): bool
    {
        return $user->id === $expenseReport->submitter_id
            && $expenseReport->currentState === ExpenseReportState::Rejected;
    }

    public function gatherEvidence(User $user, ExpenseDispute $expenseDispute): bool
    {
        return $user->id === $expenseDispute->expenseReport->submitter_id
            || $user->hasRole(UserRole::Investigator);
    }

    public function escalate(User $user, ExpenseDispute $expenseDispute): bool
    {
        return $user->hasRole(UserRole::Investigator);
    }

    public function interview(User $user, ExpenseDispute $expenseDispute): bool
    {
        return $user->hasRole(UserRole::Investigator) || $user->hasRole(UserRole::FinanceDirector);
    }

    public function resolve(User $user, ExpenseDispute $expenseDispute): bool
    {
        return $user->hasRole(UserRole::FinanceDirector);
    }
}
