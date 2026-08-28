<?php

namespace Tests\Fakes;

use App\Bpm\Contracts\ModelDefinitionGateway;
use App\Models\ModelDefinition;
use App\Models\ModelRevision;

/**
 * Stands in for the package's `ModelRegistry`, which is currently
 * stubbed. Unlike the other fakes, this one is backed by the app's own
 * `model_definitions`/`model_revisions` tables (via {@see ModelDefinition}
 * and {@see ModelRevision}) rather than a purely in-memory structure,
 * because other real rows (Instance, DecisionLog) hold foreign keys into
 * those tables and need a genuine revision id to satisfy them in tests.
 */
class FakeModelDefinitionGateway implements ModelDefinitionGateway
{
    public function resolve(string $key, ?int $revision = null): mixed
    {
        $query = ModelRevision::query()
            ->whereHas('modelDefinition', fn ($definitions) => $definitions->where('key', $key));

        if ($revision !== null) {
            return $query->where('revision_number', $revision)->first();
        }

        return $query->orderByDesc('revision_number')->first();
    }

    public function store(string $standard, string $key, string $xml): mixed
    {
        $definition = ModelDefinition::query()->firstOrCreate(
            ['key' => $key],
            ['standard' => $standard, 'name' => $key],
        );

        $nextRevisionNumber = (int) $definition->revisions()->max('revision_number') + 1;

        return $definition->revisions()->create([
            'revision_number' => $nextRevisionNumber,
            'xml' => $xml,
        ]);
    }
}
