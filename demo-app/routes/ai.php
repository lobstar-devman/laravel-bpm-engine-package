<?php

use App\Mcp\Servers\ExpenseServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/expenses', ExpenseServer::class)->middleware('auth:sanctum');
