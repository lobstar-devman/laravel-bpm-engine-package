<?php

namespace App\Bpm\Contracts;

interface ModelDefinitionGateway
{
    public function resolve(string $key, ?int $revision = null): mixed;

    public function store(string $standard, string $key, string $xml): mixed;
}
