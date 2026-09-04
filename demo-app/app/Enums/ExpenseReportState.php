<?php

namespace App\Enums;

enum ExpenseReportState: string
{
    case SubmitExpense = 'Task_SubmitExpense';
    case ManagerApproval = 'Task_ManagerReview';
    case FinanceReview = 'Task_FinanceReview';
    case Paid = 'EndEvent_Paid';
    case Rejected = 'EndEvent_Rejected';
}
