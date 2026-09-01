# Gap Analysis: Instance Identity — Model Object vs. Raw ID

**Audience:** owner(s) of the arc42 docs site (`http://host.docker.internal:8000`)
and the `lobstar/bpm-engine` package repo.

**Raised by:** the demo host app (`/app`), running real (non-faked)
integration tests against the package now that it's no longer stubbed
(`tests/Feature/Integration/RealRevisionGatewayTest.php`,
`tests/Feature/Integration/RealBulkTransitionGatewayTest.php`), per
`AGENT_INSTRUCTIONS.md`'s "Traceability" instruction to report a
specific gap rather than work around it silently.

## The gap

Section 5 documents `RevisionManager::transition(mixed $instance, string
$event)`, `RevisionManager::rollback(mixed $instance, int
$targetRevision)`, and (by extension) `QueueDispatcher::dispatchBulk(iterable
$instances, string $event)` — all typed `mixed`/untyped-element
`iterable`, with no prose stating whether `$instance` should be the
row's primary key (a UUID string) or a hydrated model representing that
row.

This app's adapters (`App\Bpm\Adapters\PackageRevisionGateway`,
`App\Bpm\Adapters\PackageBulkTransitionGateway`) forward whatever the
caller passes straight through to the package, unchanged. Every caller
in this app — `App\Mcp\Tools\SubmitExpense`,
`App\Console\Commands\EscalateOverdueExpenseReports`, the app's own
`PolicyRevisionTest` fixtures — passes the app's own `App\Models\Instance`
Eloquent model object (the row, not its id), since that object is what's
on hand at every call site and `mixed` didn't rule it out.

## Why it's load-bearing, not cosmetic

Against the real (unstubbed) package, passing the model object fails in
two different call paths:

- `RevisionManager::transition($instance, 'submit')` and
  `RevisionManager::rollback($instance, $targetRevision)` both raise:

  ```
  Illuminate\Database\Eloquent\ModelNotFoundException: No query results
  for model [Lobstar\BpmEngine\Models\Instance] <uuid>, process, submitted
  ```

  (`/package/src/Core/RevisionManager.php:168`, called from lines 46 and
  78 respectively.) The three comma-separated values in the message —
  the id, the `type`, and the `current_state` of the very
  `App\Models\Instance` object passed in — are consistent with the
  package doing its own `Lobstar\BpmEngine\Models\Instance::findOrFail($instance)`
  and Eloquent's `whereKey()` treating the incoming `Arrayable` model
  object as an array of candidate primary keys (its own `toArray()`
  values) rather than as a single key.

- `QueueDispatcher::dispatchBulk([$instance], 'escalate_to_finance')`
  raises:

  ```
  RuntimeException: QueueDispatcher::dispatchBulk() could not resolve an
  Instance for id [{"id":"...","model_revision_id":"...","type":"process","current_state":"manager_approval","created_at":"..."}]
  ```

  (`/package/src/Core/QueueDispatcher.php:60`, via line 25.) Here the
  whole model was JSON-encoded into what the package treats as a single
  "id" value, again consistent with the package expecting a scalar
  identifier per instance, not a model.

Passing the instance's `id` string instead of the model object avoids
both errors and reaches further into real package logic (confirmed via
a throwaway diagnostic, not kept in the suite) — `transition()`
progresses to `/package/src/Bpmn/BpmnInterpreter.php:25` and
`dispatchBulk()` progresses through the real queued `BatchTransitionJob`
into `ModelRegistry`/`BpmnParser` — strongly suggesting the package's
real, intended contract is **pass the ID**, not the model.

## What's specifically missing to close the gap

1. An explicit statement in Section 5 that `$instance` (and each element
   of `$instances`) is the instance's primary key (string/UUID), not a
   hydrated model — or, if the package intends to accept either, that
   `RevisionManager`/`QueueDispatcher` should type/coerce accordingly
   instead of feeding the raw argument into `findOrFail()`/JSON-encoding
   it.
2. Confirmation of whether this is the intended contract or a bug in
   how `RevisionManager`/`QueueDispatcher` resolve their `$instance`
   argument.

## Where we looked and found nothing

Per this repo's cross-repo isolation policy (`CLAUDE.md`), we did not
open `RevisionManager.php`, `QueueDispatcher.php`, or `BpmnInterpreter.php`
to confirm the exact internal cause beyond what appeared unprompted in
exception stack traces during test execution. The evidence above is
everything visible from the package's public API and its runtime error
output.

## Suggested resolution path

If "pass the ID" is confirmed as the intended contract, this app's three
call sites (`SubmitExpense`, `EscalateOverdueExpenseReports`, and any
future rollback flow from `docs/gap-analysis/revision-resolution.md`
step 7) need to pass `$instance->id` / `$expenseReport->instance_id`
rather than the model, and the adapters'/contracts' `mixed`/`iterable`
signatures should gain a docblock recording that requirement. Until
then, `tests/Feature/Integration/RealRevisionGatewayTest.php` and
`RealBulkTransitionGatewayTest.php` are left red intentionally, mirroring
the app's actual current production call shape, as regression/contract
checks that will start passing once one side or the other changes.
