<?php

namespace App\Bpm\Adapters;

use App\Bpm\Contracts\ModelDefinitionGateway;
use Lobstar\BpmEngine\Core\ModelRegistry;

class PackageModelDefinitionGateway implements ModelDefinitionGateway
{
    public function __construct(protected ModelRegistry $modelRegistry) {}

    public function resolve(string $key, ?int $revision = null): mixed
    {
        return $this->modelRegistry->resolve($key, $revision);
    }

    public function store(string $standard, string $key, string $xml): mixed
    {
        return $this->modelRegistry->store($standard, $key, $xml);
    }
}
