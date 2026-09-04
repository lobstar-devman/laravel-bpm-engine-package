<?php

namespace App\Policies;

use App\Enums\ExpenseReportState;
use App\Enums\UserRole;
use App\Models\ExpenseReport;
use App\Models\User;

class ExpenseReportPolicy
{
    public function submit(User $user): bool
    {
        return $user->hasRole(UserRole::Employee);
    }

    public function approve(User $user, ExpenseReport $expenseReport): bool
    {
        return $user->hasRole(UserRole::Manager)
            && $user->id === $expenseReport->manager_id
            && $expenseReport->currentState === ExpenseReportState::ManagerApproval;
    }

    public function reject(User $user, ExpenseReport $expenseReport): bool
    {
        return $user->hasRole(UserRole::Manager)
            && $user->id === $expenseReport->manager_id
            && $expenseReport->currentState === ExpenseReportState::ManagerApproval;
    }

    public function financeApprove(User $user, ExpenseReport $expenseReport): bool
    {
        return $user->hasRole(UserRole::Finance)
            && $expenseReport->currentState === ExpenseReportState::FinanceReview;
    }

    public function financeReject(User $user, ExpenseReport $expenseReport): bool
    {
        return $user->hasRole(UserRole::Finance)
            && $expenseReport->currentState === ExpenseReportState::FinanceReview;
    }

    /**
     * Manual, single-instance escalation to Finance — the assigned
     * manager or any Finance user, while still in Manager Approval.
     */
    public function escalate(User $user, ExpenseReport $expenseReport): bool
    {
        if ($expenseReport->currentState !== ExpenseReportState::ManagerApproval) {
            return false;
        }

        return ($user->hasRole(UserRole::Manager) && $user->id === $expenseReport->manager_id)
            || $user->hasRole(UserRole::Finance);
    }
}
