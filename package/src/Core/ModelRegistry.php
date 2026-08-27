<?php

namespace Lobstar\BpmEngine\Core;

/**
 * Loads, validates, and stores BPMN/CMMN/DMN XML definitions and their
 * revisions. See docs/arc42/05-building-block-view.md.
 */
class ModelRegistry
{
    public function resolve(string $key, ?int $revision = null): mixed
    {
        throw new \RuntimeException('Not implemented yet.');
    }

    public function store(string $standard, string $key, string $xml): mixed
    {
        throw new \RuntimeException('Not implemented yet.');
    }
}
