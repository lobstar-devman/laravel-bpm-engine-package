<?php

namespace Database\Factories;

use App\Models\ModelDefinition;
use App\Models\ModelRevision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ModelRevision>
 */
class ModelRevisionFactory extends Factory
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
            'model_definition_id' => ModelDefinition::factory(),
            'revision_number' => 1,
            'xml' => '<?xml version="1.0" encoding="UTF-8"?><definitions/>',
        ];
    }
}
