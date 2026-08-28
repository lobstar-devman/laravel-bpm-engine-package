<?php

namespace Database\Factories;

use App\Models\ModelDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ModelDefinition>
 */
class ModelDefinitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'standard' => 'bpmn',
            'key' => 'expense_reimbursement',
            'name' => 'Expense Reimbursement',
        ];
    }
}
