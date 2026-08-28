<?php

namespace App\Enums;

enum UserRole: string
{
    case Employee = 'employee';
    case Manager = 'manager';
    case Finance = 'finance';
    case Investigator = 'investigator';
    case FinanceDirector = 'finance_director';
}
