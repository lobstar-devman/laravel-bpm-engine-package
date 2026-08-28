<?php

namespace App\Bpm\Adapters;

use App\Bpm\Contracts\RevisionGateway;
use Lobstar\BpmEngine\Core\RevisionManager;

class PackageRevisionGateway implements RevisionGateway
{
    public function __construct(protected RevisionManager $revisionManager) {}

    public function transition(mixed $instance, string $event): mixed
    {
        return $this->revisionManager->transition($instance, $event);
    }

    public function rollback(mixed $instance, int $targetRevision): mixed
    {
        return $this->revisionManager->rollback($instance, $targetRevision);
    }
}
