<?php

namespace Database\Factories;

use App\Models\ExpenseReport;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseReport>
 */
class ExpenseReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instance_id' => Instance::factory(),
            'submitter_id' => User::factory(),
            'manager_id' => User::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 5000),
            'category' => $this->faker->randomElement(['travel', 'meals', 'supplies', 'software']),
            'submitted_at' => now(),
        ];
    }
}
