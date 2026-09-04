# Gap Analysis: Deriving `Instance.model_revision_id` at Creation Time

**Audience:** owner(s) of the arc42 docs site (`http://host.docker.internal:8000`)
and the `lobstar/bpm-engine` package repo.

**Raised by:** a production error reported via the MCP inspector —
`SubmitExpense` failed with a `NOT NULL constraint failed:
instances.model_revision_id` even though `model_definitions` was
correctly seeded. Investigated per `AGENT_INSTRUCTIONS.md`'s
"Traceability" instruction.

## The gap

`SubmitExpense` and `OpenDispute` are the only two tools that create a
fresh `Instance`, and both needed a real `model_revision_id` to satisfy
that column's FK/NOT NULL constraint before the first `transition()`
call. Both derived it the same way:

```php
$revision = $this->modelDefinitionGateway->resolve('expense_reimbursement');
$instance = Instance::create(['model_revision_id' => RevisionId::from($revision), ...]);
```

`RevisionId::from()` defensively extracted `->id` from whatever
`resolve()` returned, because — per this app's own docblock —
"`ModelDefinitionGateway::resolve()`/`store()` return `mixed`, mirroring
the package's own untyped `ModelRegistry` signatures, so their concrete
shape is unknown until the package is unstubbed."

Now that the package is unstubbed, ADR-012 ("BPMN Vocabulary Hash")
confirms `ModelRegistry::resolve()` returns the **parsed** process/case
model (`BpmnProcessModel`, cached per revision) — not a `ModelRevision`
record. It has no `id` property at all, so `RevisionId::from()` silently
returned `null`, and `Instance::create()` failed the NOT NULL
constraint the first time either tool ran against the real gateways.

## Why this passed the full test suite

Every test covering `SubmitExpense`/`OpenDispute` uses
`UsesFakeBpmGateways`. `Tests\Fakes\FakeModelDefinitionGateway::resolve()`
returns a real `App\Models\ModelRevision` Eloquent row (it needs to,
per its own docblock, "because other real rows ... hold foreign keys
into those tables and need a genuine revision id to satisfy them in
tests") — which does have `->id`. The fake's `resolve()` shape diverges
from the real package's documented `resolve()` shape, and nothing
exercised these two tools against the real gateways until now, so the
divergence went undetected by an otherwise fully-green suite.

## What we changed here

`SubmitExpense`/`OpenDispute` no longer call
`ModelDefinitionGateway::resolve()` at all for this purpose — they don't
need the parsed model, only the current revision's id, which they now
read directly from the app's own `App\Models\ModelDefinition`/`ModelRevision`
Eloquent models (the same "one-model-per-package-table" pattern already
used elsewhere, e.g. `RealModelDefinitionGatewayTest`):

```php
$revision = ModelDefinition::where('key', 'expense_reimbursement')
    ->firstOrFail()
    ->revisions()
    ->latest('revision_number')
    ->firstOrFail();

Instance::create(['model_revision_id' => $revision->id, ...]);
```

The now-dead, now-incorrect `App\Bpm\Support\RevisionId` class was
deleted, and the unused `ModelDefinitionGateway` constructor dependency
was removed from both tools.

`tests/Feature/Integration/RealSubmitExpenseGatewayTest.php` exercises
`SubmitExpense` through the real `ModelDefinitionGateway`/`RevisionGateway`
bindings and is green. The equivalent real test for `OpenDispute`
(`RealOpenDisputeGatewayTest.php`) still fails, but for an unrelated,
separate reason — see
`docs/gap-analysis/cmmn-ad-hoc-case-plan-unsupported.md`.

## What's specifically missing to close the gap upstream

1. `ModelDefinitionGateway`/`ModelRegistry::resolve(): mixed` is still
   untyped in this app's own contract. Now that its real return shape is
   documented (ADR-012, for BPMN), the app's own
   `App\Bpm\Contracts\ModelDefinitionGateway` interface could narrow
   `resolve()`'s return type — not done here, to keep this change scoped
   to the actual bug.
2. `FakeModelDefinitionGateway::resolve()` still returns a
   `ModelRevision`, diverging from the real package's parsed-model
   return. Nothing currently calls it, so it's dormant, not actively
   wrong — but it would silently reinforce the same incorrect assumption
   for any future caller. Worth fixing or removing if a real use for the
   fake's `resolve()` ever reappears.

## Suggested resolution path

Nothing further needed on the package side — this was purely an app-side
mismatch between a stale fake and the now-documented real behavior. The
two real tests above (`RealSubmitExpenseGatewayTest`,
`RealOpenDisputeGatewayTest`) are the durable regression guard.
