<?php

namespace Lobstar\BpmEngine\Bpmn;

/**
 * A parsed BPMN process's canonical, stable vocabulary: every node id
 * and every distinct sequence-flow name — the literal terms host-app
 * code is expected to hard-code against (e.g. Instance::current_state
 * comparisons, or the event name BpmnInterpreter::drive() matches
 * against a flow). Node display names and Lane/Pool role are
 * deliberately excluded — cosmetic/organizational metadata that
 * BpmnInterpreter never matches against (role per ADR-005).
 *
 * Unlike BpmnNode/BpmnFlow, this is a stable, versioned public
 * contract — see ADR-012.
 */
final class BpmnVocabulary
{
    private const HASH_ALGORITHM = 'sha256';

    /**
     * @param  list<string>  $nodeIds  sorted ascending, SORT_STRING
     * @param  list<string>  $flowNames  distinct, sorted ascending, SORT_STRING
     */
    public function __construct(
        public readonly array $nodeIds,
        public readonly array $flowNames,
    ) {}

    /** Deterministic JSON serialization — fixed field/array order — used as hash()'s input. */
    public function toCanonicalJson(): string
    {
        return json_encode(
            ['nodeIds' => $this->nodeIds, 'flowNames' => $this->flowNames],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Digest of toCanonicalJson(), prefixed with the algorithm name
     * (e.g. "sha256:...") so a future algorithm change is
     * self-describing rather than silently comparable against the
     * wrong kind of hash.
     */
    public function hash(): string
    {
        return self::HASH_ALGORITHM.':'.hash(self::HASH_ALGORITHM, $this->toCanonicalJson());
    }
}
