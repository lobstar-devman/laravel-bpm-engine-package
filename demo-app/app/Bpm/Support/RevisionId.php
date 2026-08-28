<?php

namespace App\Bpm\Support;

use App\Models\ModelRevision;

/**
 * `ModelDefinitionGateway::resolve()`/`store()` return `mixed`,
 * mirroring the package's own untyped `ModelRegistry` signatures, so
 * their concrete shape is unknown until the package is unstubbed.
 * Centralizes the defensive extraction of a revision id from whatever
 * comes back — a real {@see ModelRevision} (as the fake gateway
 * returns), a plain array, or any other object carrying an `id`.
 */
class RevisionId
{
    public static function from(mixed $revision): ?string
    {
        if ($revision instanceof ModelRevision) {
            return $revision->id;
        }

        if (is_array($revision)) {
            return $revision['id'] ?? null;
        }

        if (is_object($revision) && isset($revision->id)) {
            return $revision->id;
        }

        return null;
    }
}
