<?php

namespace Lobstar\BpmEngine\Core;

use Illuminate\Support\Str;
use Lobstar\BpmEngine\Bpmn\BpmnParser;
use Lobstar\BpmEngine\Cmmn\CmmnParser;
use Lobstar\BpmEngine\Dmn\DmnParser;
use Lobstar\BpmEngine\Models\ModelDefinition;
use Lobstar\BpmEngine\Models\ModelRevision;

/**
 * Loads, validates, and stores BPMN/CMMN/DMN XML definitions and their
 * revisions. See docs/arc42/05-building-block-view.md.
 */
class ModelRegistry
{
    /** @var array<string, mixed> parsed model, keyed by model_revision id */
    private array $parsedCache = [];

    public function __construct(
        private readonly BpmnParser $bpmnParser,
        private readonly CmmnParser $cmmnParser,
        private readonly DmnParser $dmnParser,
    ) {}

    /**
     * Resolves $key's revision (the latest one, unless $revision is
     * given), parsing its XML via the standard-appropriate Parser —
     * cached per revision — and returns the parsed model.
     */
    public function resolve(string $key, ?int $revision = null): mixed
    {
        $definition = ModelDefinition::where('key', $key)->firstOrFail();

        $revisionModel = $revision === null
            ? ModelRevision::where('model_definition_id', $definition->id)
                ->orderByDesc('revision_number')
                ->firstOrFail()
            : ModelRevision::where('model_definition_id', $definition->id)
                ->where('revision_number', $revision)
                ->firstOrFail();

        return $this->parsedCache[$revisionModel->id] ??= $this->parse($definition->standard, $revisionModel->xml);
    }

    /** Stores $xml as the next revision of the $standard model definition identified by $key. */
    public function store(string $standard, string $key, string $xml): mixed
    {
        $definition = ModelDefinition::firstOrCreate(
            ['key' => $key],
            ['id' => (string) Str::uuid(), 'standard' => $standard, 'name' => $key],
        );

        $nextRevisionNumber = ((int) ModelRevision::where('model_definition_id', $definition->id)->max('revision_number')) + 1;

        return ModelRevision::create([
            'id' => (string) Str::uuid(),
            'model_definition_id' => $definition->id,
            'revision_number' => $nextRevisionNumber,
            'xml' => $xml,
        ]);
    }

    private function parse(string $standard, string $xml): mixed
    {
        return match ($standard) {
            'bpmn' => $this->bpmnParser->parse($xml),
            'cmmn' => $this->cmmnParser->parse($xml),
            'dmn' => $this->dmnParser->parse($xml),
            default => throw new \InvalidArgumentException("Unknown standard [{$standard}]."),
        };
    }
}
