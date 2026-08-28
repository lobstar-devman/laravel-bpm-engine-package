<?php

namespace App\Bpm\Contracts;

interface RevisionGateway
{
    public function transition(mixed $instance, string $event): mixed;

    public function rollback(mixed $instance, int $targetRevision): mixed;
}
