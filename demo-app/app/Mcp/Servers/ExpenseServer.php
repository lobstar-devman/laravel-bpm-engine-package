<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ApproveExpense;
use App\Mcp\Tools\EscalateToFinance;
use App\Mcp\Tools\OpenDispute;
use App\Mcp\Tools\RejectExpense;
use App\Mcp\Tools\SubmitExpense;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Expense Server')]
#[Version('0.0.1')]
#[Instructions('Domain-verbed tools over the Expense Reimbursement scenario. Each tool authorizes the request before calling into the BPM engine — see ADR-005/ADR-006.')]
class ExpenseServer extends Server
{
    protected array $tools = [
        SubmitExpense::class,
        ApproveExpense::class,
        RejectExpense::class,
        EscalateToFinance::class,
        OpenDispute::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
