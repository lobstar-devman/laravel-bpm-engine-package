<?php

namespace App\Bpm\Adapters;

use App\Bpm\Contracts\RevisionGateway;
use App\Bpm\ValueObjects\InstanceId;
use Lobstar\BpmEngine\Core\RevisionManager;

class PackageRevisionGateway implements RevisionGateway
{
    public function __construct(protected RevisionManager $revisionManager) {}

    public function transition(InstanceId $instance, string $event): mixed
    {
        return $this->revisionManager->transition((string) $instance, $event);
    }

    public function rollback(InstanceId $instance, int $targetRevision): mixed
    {
        return $this->revisionManager->rollback((string) $instance, $targetRevision);
    }
}
