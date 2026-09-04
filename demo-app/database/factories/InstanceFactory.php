<?php

namespace Database\Factories;

use App\Enums\ExpenseDisputeState;
use App\Enums\ExpenseReportState;
use App\Models\Instance;
use App\Models\ModelRevision;
use BackedEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Instance>
 */
class InstanceFactory extends Factory
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
            'model_revision_id' => ModelRevision::factory(),
            'type' => 'process',
            'current_state' => ExpenseReportState::ManagerApproval->value,
        ];
    }

    public function process(): static
    {
        return $this->state(['type' => 'process']);
    }

    public function case(): static
    {
        return $this->state(['type' => 'case']);
    }

    public function withState(ExpenseReportState|ExpenseDisputeState|string $state): static
    {
        return $this->state(['current_state' => $state instanceof BackedEnum ? $state->value : $state]);
    }
}
