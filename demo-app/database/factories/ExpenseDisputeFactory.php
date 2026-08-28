<?php

namespace Database\Factories;

use App\Models\ExpenseDispute;
use App\Models\ExpenseReport;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseDispute>
 */
class ExpenseDisputeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instance_id' => Instance::factory()->case()->withState('open'),
            'expense_report_id' => ExpenseReport::factory(),
            'opened_by' => User::factory(),
        ];
    }
}
