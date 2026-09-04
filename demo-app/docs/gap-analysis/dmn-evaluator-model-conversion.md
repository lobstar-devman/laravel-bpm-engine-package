# Gap Analysis: `DmnEvaluator::evaluate()` Rejects `ModelRegistry::resolve()`'s Own Output

**Audience:** owner(s) of the arc42 docs site (`http://host.docker.internal:8000`)
and the `lobstar/bpm-engine` package repo.

**Raised by:** the demo host app (`/app`), running real (non-faked)
integration tests against the package now that it's no longer stubbed
(`tests/Feature/Integration/RealDecisionGatewayTest.php`), per
`AGENT_INSTRUCTIONS.md`'s "Traceability" instruction to report a
specific gap rather than work around it silently.

## The gap

Section 5 lists `DmnEvaluator::evaluate(mixed $model, array $inputData):
array` and `ModelRegistry::resolve(string $key, ?int $revision = null):
mixed` as the documented, stable public API for the DMN decision step.
`App\Domain\Expenses\Services\AutoApprovalDecisionService::evaluate()`
chains them exactly as their signatures suggest: resolve the current
`auto_approval_threshold` revision, then hand that resolved value
straight into `evaluate()` as `$model`.

## Why it's load-bearing, not cosmetic

Against the real (unstubbed) package, this chain throws before ever
producing a decision:

```
Error: Object of class Lobstar\BpmEngine\Dmn\DmnDecisionModel could not
be converted to string
```

Stack trace (unprompted, from test execution):

```
/package/src/Dmn/DmnEvaluator.php:33
/app/app/Bpm/Adapters/PackageDecisionGateway.php:14
/app/app/Domain/Expenses/Services/AutoApprovalDecisionService.php:36
```

`ModelRegistry::resolve('auto_approval_threshold')` returns a real
`Lobstar\BpmEngine\Dmn\DmnDecisionModel` object (confirmed via a
throwaway diagnostic, not kept in the suite) — i.e. `resolve()` for a
`dmn`-standard key returns an already-parsed decision model, not the raw
`ModelRevision` row that `store()` returns for the same key. Something
inside `DmnEvaluator::evaluate()` at line 33 then attempts to convert
that `DmnDecisionModel` object to a string, which PHP rejects because it
has no `__toString()`.

This blocks every consumer that follows the documented `resolve()` →
`evaluate()` sequence — there is no alternative documented path to
evaluate a DMN decision.

## What's specifically missing to close the gap

1. Confirmation of whether `DmnEvaluator::evaluate()` is meant to accept
   the object `ModelRegistry::resolve()` returns for a `dmn` key
   directly, or expects a different shape (e.g. the DMN XML as a string,
   or the `ModelRevision` row rather than a parsed `DmnDecisionModel`).
2. If the two methods are meant to chain as their signatures suggest,
   this looks like a bug in `DmnEvaluator::evaluate()` (or in whatever
   it calls) doing an implicit string cast of `$model` — worth a fix
   rather than a docs change.

## Where we looked and found nothing

Per this repo's cross-repo isolation policy (`CLAUDE.md`), we did not
open `DmnEvaluator.php` to find the exact line causing the cast beyond
what the exception message and stack trace already show unprompted.

## Suggested resolution path

Once resolved, `tests/Feature/Integration/RealDecisionGatewayTest.php`
(currently red, asserting the actual documented business rules from
`resources/bpm/auto-approval-threshold.dmn.xml` — under/at 500 auto-
approves, over 500 doesn't, travel never does) should go green
unchanged, as a contract check against whichever fix lands.

## Resolution (part 1): the app was calling the wrong shape

The package repo confirmed this was a contract mismatch, not a package
defect: the arc42 Section 6 sequence diagram
(`diagrams/mermaid/runtime-dmn-evaluation.svg`) documents the call as
`evaluate(decisionKey, inputData)` — a **string key**, with the
Evaluator resolving the current revision internally — not
`resolve()`'s return value fed into `evaluate()`. `resolve()` →
`evaluate()` chaining was this app's own incorrect assumption, not the
documented flow.

Fixed app-side: `App\Bpm\Contracts\DecisionGateway::evaluate()` and
`App\Bpm\Adapters\PackageDecisionGateway` now take
`evaluate(string $decisionKey, array $inputData): array`, and
`AutoApprovalDecisionService` passes the literal key
(`'auto_approval_threshold'`) rather than a resolved model. The package
also tightened `DmnEvaluator::evaluate()` to throw a clear
`InvalidArgumentException` naming the correct call shape instead of the
raw string-conversion `Error`, for any future consumer that makes the
same mistake.

## New finding uncovered by the fix: `resolve()`'s DMN return value carries no revision identity

With the call-shape fixed, `RealDecisionGatewayTest` progresses past the
`DmnEvaluator` crash entirely, but now fails differently:

```
SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint
failed: decision_logs.model_revision_id
```

`AutoApprovalDecisionService` still calls
`$this->modelDefinitionGateway->resolve('auto_approval_threshold')`
separately (independent of the `evaluate()` call) purely to get a
revision id for the `DecisionLog.model_revision_id` FK.
`App\Bpm\Support\RevisionId::from($revision)` returns `null` here
because — confirmed via public reflection only, no `DmnEvaluator.php`
source read —

```
CLASS: Lobstar\BpmEngine\Dmn\DmnDecisionModel
PUBLIC METHODS: __construct(string $hitPolicy, array $inputExpressions, array $outputNames, array $rules)
PUBLIC PROPS: hitPolicy, inputExpressions, outputNames, rules
```

— `resolve()`'s return value for a `dmn` key is a pure parsed
decision-table value object with **no identity information at all**: no
`id`, no revision reference, nothing traceable back to the
`model_revisions` row it came from.

This raises a real question this app has no documented way to answer:
how is a consumer meant to know which revision governed a given
evaluation, for its own audit logging? Two possibilities:

1. `ADR-009` ("No Decision Manager — DMN Evaluator resolves, evaluates,
   and logs directly") suggests `DmnEvaluator::evaluate()` may already
   write the `decision_logs` row itself internally — in which case this
   app's own `DecisionLog::create()` call in `AutoApprovalDecisionService`
   is redundant/duplicative and should be removed, not fixed. But the
   documented `evaluate(decisionKey, inputData)` signature has no
   `$instance`/`$instanceId` parameter, so it's unclear how it would
   know which instance to log against.
2. Alternatively, `ModelDefinitionGateway::resolve()` may need a
   different, not-yet-documented return shape or sibling method for
   `dmn` keys that does carry a revision id, distinct from the parsed
   model `evaluate()` consumes.

Left as an open question for whoever owns `/package` and the docs site
— this app has not modified `AutoApprovalDecisionService`'s
`DecisionLog::create()` call further, to avoid guessing at an
architecture decision (ADR-009's actual scope) from outside.
`RealDecisionGatewayTest` remains red for this reason.

## A second, smaller finding visible in the same evidence

The failed insert's bound values also show the real `evaluate()`'s
output array keyed by an **empty string**, not `auto_approve`:
`{"":true}` for an under-threshold, non-travel expense (amount 42.50,
software) and `{"":false}` for an over-threshold or travel expense
(amount 1000 software; amount 10 travel) — correct *boolean* outcomes
per the business rules authored in
`resources/bpm/auto-approval-threshold.dmn.xml`
(`<output id="Output_AutoApprove" label="auto_approve" .../>`), but not
retrievable via `$outputs['auto_approve']` as
`AutoApprovalDecisionService` (and this DMN's own `label` attribute)
expect. Once the `model_revision_id` question above is resolved, this
would still make `AutoApprovalDecisionService::evaluate()` always return
`false` (the `$outputs['auto_approve'] ?? false` fallback). Whether the
real evaluator should key its output array by the DMN output's `id`,
its `label`, or something else entirely is a second, smaller question
for the same audience.

## Resolution (part 2): both follow-up findings closed

The package repo confirmed both:

- **Decision Log ownership:** `DmnEvaluator::evaluate()` does write its
  own `decision_logs` row internally on every call (inputs, outputs,
  and an internally-resolved `model_revision_id`), confirming the
  ADR-009 hypothesis above. `instance_id` is hardcoded to `null` in that
  write — the documented `evaluate(decisionKey, inputData)` signature
  has no instance parameter, so there is currently no way to correlate
  a decision log to a specific process instance. That's a genuine
  capability gap (a real feature request, not a bug) rather than
  something to work around; noted below as still open. Fixed app-side:
  `AutoApprovalDecisionService` no longer calls
  `ModelDefinitionGateway::resolve()` or `DecisionLog::create()` at
  all — the package's own write is now the single source of truth for
  this log, and the service is just `evaluate(decisionKey, inputs):
  bool`.
- **Empty-string output key:** confirmed as a real package bug, now
  fixed package-side. Root cause: `DmnParser` was reading an
  `<output>`'s `name` attribute only; this app's DMN set `label`
  (display-only, per DMN 1.3) but not `name` (the actual identifier),
  so it silently parsed as an empty-string key. Per the DMN authoring
  fix on this app's side, `resources/bpm/auto-approval-threshold.dmn.xml`'s
  `<output>` now carries both `name="auto_approve"` and the existing
  `label="auto_approve"`. Package-side, `DmnParser` now throws
  `InvalidArgumentException` at parse time for any `<output>` missing a
  non-empty `name`, instead of silently succeeding with a useless key —
  consistent with ADR-008's "fail loudly" stance.

All four `RealDecisionGatewayTest` cases are green as of this fix. The
`instance_id`-correlation gap noted above remains open and would need
its own ADR/signature-change discussion if this app (or another
consumer) needs instance-correlated decision logs — not pursued further
here since nothing in this scenario currently depends on it.
