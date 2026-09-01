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
