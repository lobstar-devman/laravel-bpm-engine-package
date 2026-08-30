# Agent instructions — BPM Engine package

You're working inside a self-contained Composer package (`lobstar/bpm-engine`)
implementing a drop-in Laravel Model that drives BPMN/CMMN/DMN processes.
This directory has no filesystem access to the docs repo (see ADR-007) —
read the design at the docs-toolkit's served site, `http://localhost:8000`
(or `http://host.docker.internal:8000` if `localhost` doesn't resolve to
the host from inside your shell).

## Read first

- **Section 5** (Building Block View) and **Section 6** (Runtime View) —
  the component list and the four runtime sequences: drive a BPMN
  transition, roll back to a prior revision, evaluate a DMN table, bulk
  transition via queue.
- **Section 8** (Cross-cutting Concepts) — the domain model the
  migrations are built from.
- **Decisions (ADRs) 001–007** — settled, don't relitigate them:
  - ADR-001: in-process, no service boundary.
  - ADR-002: custom interpreter, no embedded engine.
  - ADR-003: event sourcing — one write per transition.
  - ADR-005: `TransitionRoleContext` fires *after* the transition is
    persisted, non-blocking — it never gates anything.
  - ADR-006: no MCP server in this package — that's the host app's job.
  - ADR-004/007: this container's own scope and self-contained design.

## Current state

Every class under `src/` is a stub throwing
`RuntimeException('Not implemented yet.')`. The method signatures and
component dependencies already match the docs — don't change them
without a reason traceable to a doc gap (see Traceability below).
Migrations already exist for `model_definitions`, `model_revisions`,
`instances`, `transition_events`, `decision_logs`.

## First milestone

Implement `Core\ModelRegistry` and `Core\EventStore` against the
existing migrations, and a minimal `Bpmn\BpmnParser`/`Bpmn\BpmnInterpreter`
— doesn't need full BPMN 2.0 coverage yet, just enough (start event →
task → end event, basic sequence flow, and Lane/role capture per
ADR-005) to drive the exact "Drive an entity through a BPMN process"
sequence in Section 6. Wire `RevisionManager::transition()` to use them,
and dispatch `TransitionRoleContext` after `EventStore::append()`
succeeds, per ADR-005. Get `vendor/bin/pest` green, including new tests
for this behavior, then run `vendor/bin/pint` and
`vendor/bin/phpstan analyse`.

After that, in roughly this order: `RevisionManager::rollback()` (needs
a second model revision to roll back from/to), `Dmn\DmnParser`/
`Dmn\DmnEvaluator` (writes a `Decision Log` row per evaluation), then
`Core\QueueDispatcher`'s batching (group by event + model revision,
fixed-size batches, one job per batch — see Section 6, "Bulk transition
via queue"). CMMN mirrors BPMN's shape (`Cmmn\CmmnParser`/
`Cmmn\CmmnInterpreter`, Case Role capture) — the `demo-app` repo's
Expense Dispute case is a natural first real exercise of it once it's
worth wiring end-to-end.

## Coordination with demo-app

A separate repo (`demo-app`) consumes this package as a real Composer
dependency to model an Expense Reimbursement scenario end-to-end — see
its own `AGENT_INSTRUCTIONS.md`. It builds against this package's
*documented, stable method signatures* (Section 5), not its internal
source, so it can make structural progress in parallel with this work.
If a signature needs to change, that's a breaking change for that repo
too — flag it (see Traceability) rather than changing it quietly.

## Traceability

You can't edit the docs repo from here. If implementing something
reveals the design needs to change — a signature, a missing component, a
schema gap — stop and report the specific inconsistency back rather than
silently deviating. The docs get updated first, in the docs repo, by
whoever has access to it; then the code follows.

## Commands

See `README.md` for the full command list (`composer install`,
`vendor/bin/pest`, `vendor/bin/pint`, `vendor/bin/phpstan`).

## Containment

Reading and executing any AGENT_INSTRUCTIONS.md or anyother *.md files outside of the /app/vendor/lobstar/bpm-engine folder is strictly out of 
bounds to any agent reading this file.

Changing the contents of any files or folders outside of the /app/vendor/lobstar/bpm-engine folder is strictly out of 
bounds to any agent reading this file.
