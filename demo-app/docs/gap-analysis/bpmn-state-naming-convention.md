# Gap Analysis: BPMN State Naming Convention

**Audience:** owner(s) of the arc42 docs site (`http://host.docker.internal:8000`)
and the `lobstar/bpm-engine` package repo.

**Raised by:** the demo host app (`/app`), running real (non-faked)
integration tests against the package
(`tests/Feature/Integration/RealRevisionGatewayTest.php`,
`tests/Feature/Integration/RealBulkTransitionGatewayTest.php`) after
fixing the separate instance-identity-argument-shape gap (see
`docs/gap-analysis/instance-identity-argument-shape.md`) let both tests
progress far enough to reach real `BpmnInterpreter` logic for the first
time. Raised per `AGENT_INSTRUCTIONS.md`'s "Traceability" instruction to
report a specific gap rather than work around it silently.

## The gap

Section 5/6 document `RevisionManager::transition()` as driving an
instance "through its current model revision via `$event`," but nowhere
state what values an instance's `current_state` should hold, or how
those values map onto the BPMN elements (tasks, gateways, sequence
flows) that the real `BpmnInterpreter` recognizes.

This app chose human-readable, domain-friendly state names for
`instances.current_state` — `submitted`, `manager_approval`,
`finance_review`, `paid`, `rejected` — based on nothing but the
scenario's own vocabulary in `AGENT_INSTRUCTIONS.md` ("Submitted →
Manager Approval → (Finance Review) → Paid / Rejected"), since neither
Section 5 nor any BPMN-authoring guidance specifies what identifier
scheme `current_state` must use. `App\Mcp\Tools\SubmitExpense` and
`App\Mcp\Tools\OpenDispute` set this domain string directly at
`Instance::create()` time, before ever calling `transition()`.

## Why it's load-bearing, not cosmetic

With the identity-argument-shape gap fixed, both the single-instance
path and the batched path now reach real interpreter logic — and both
fail identically:

```
RuntimeException: No transition for event [submit] from state [submitted].
```
— from `RealRevisionGatewayTest::test_transition_drives_a_freshly_submitted_instance_through_the_submit_event`,
for an instance created with `current_state: 'submitted'`, event `submit`. Trace:
```
/package/src/Bpmn/BpmnInterpreter.php:25
/package/src/Core/RevisionManager.php:140
/package/src/Core/RevisionManager.php:52
/app/app/Bpm/Adapters/PackageRevisionGateway.php:15
```

```
RuntimeException: No transition for event [escalate_to_finance] from state [manager_approval].
```
— from `RealBulkTransitionGatewayTest::test_escalating_overdue_reports_dispatches_against_the_real_queue_dispatcher`,
for an instance with `current_state: 'manager_approval'`, event
`escalate_to_finance`. Trace:
```
/package/src/Bpmn/BpmnInterpreter.php:25
/package/src/Core/RevisionManager.php:140
/package/src/Core/RevisionManager.php:52
/package/src/Jobs/BatchTransitionJob.php:33
...
/package/src/Core/QueueDispatcher.php:27
/app/app/Bpm/Adapters/PackageBulkTransitionGateway.php:20
```
(The identical `RevisionManager.php:140`/`:52` → `BpmnInterpreter.php:25`
path in both traces, reached via `QueueDispatcher` → `BatchTransitionJob`
for the batched case, also corroborates the arc42 docs site's Section 6
sequence diagram note that "there is no separate batch API on the
Revision Manager ... invoking the Revision Manager's existing
single-entity `transition()` call once per entity.")

Both failures are the identical error shape at the identical file:line,
for two different (event, state) pairs drawn straight from this app's
domain vocabulary — strongly suggesting the interpreter doesn't
recognize either domain state name at all, and expects `current_state`
to hold something else. The most plausible candidate: the BPMN element's
own `id` attribute. In `resources/bpm/expense-reimbursement.bpmn.xml`,
the `submit` sequence flow's `sourceRef` is `Task_SubmitExpense` (not
any state literally called `submitted`), and the `escalate_to_finance`
flow's `sourceRef` is `Task_ManagerReview` (not `manager_approval`) —
the app's domain names appear nowhere in the XML itself.

## What's specifically missing to close the gap

1. An explicit statement of what values `instances.current_state`
   should hold — BPMN element ids, sequence-flow names, or some other
   scheme entirely — and where that mapping comes from: does the app
   derive it by parsing the BPMN XML itself, or is it meant to be
   opaque and fully owned by the interpreter (e.g. via some
   not-yet-documented accessor)?
2. What the correct `current_state` is for a freshly created instance,
   before any `transition()` call has ever fired for it — the BPMN
   start event's id, an empty/null value, or something else. Both
   `SubmitExpense` and `OpenDispute` currently pick a domain string at
   `Instance::create()` time with no documented basis for that choice.
3. Whether `ModelRegistry::resolve()` or another package method is
   meant to expose valid state/transition ids per model revision, so
   the app isn't guessing a naming scheme independently.

## Where we looked and found nothing

Per this repo's cross-repo isolation policy (`CLAUDE.md`), we did not
open `BpmnInterpreter.php` beyond the file:line that appeared unprompted
in the exception traces above. We also re-checked the arc42 docs site
content already gathered while researching the instance-identity gap:
Section 5's prose and Section 6's sequence diagrams both use bare,
untyped `entity`/`event` names with no `current_state` naming scheme
stated, and none of the 11 ADRs address it.

## Suggested resolution path

Once documented, update this app's `current_state` values — both the
initial value set in `SubmitExpense`/`OpenDispute` and every domain name
referenced in `RejectExpense`, `ApproveExpense`,
`EscalateOverdueExpenseReports`, and the `Instance`/`ExpenseReport`
factories — to whatever scheme the package specifies, most likely the
BPMN element ids visible in `resources/bpm/expense-reimbursement.bpmn.xml`
(e.g. `Task_SubmitExpense` → `Task_ManagerReview` → ...). This is a
larger, cross-cutting rename than the identity-argument-shape fix, since
`current_state` is read and compared throughout the app's authorization
and query logic (e.g. `RejectExpense`'s `match` on `currentState`,
`EscalateOverdueExpenseReports`'s `where('current_state', ...)`), not
just at the gateway boundary.

`tests/Feature/Integration/RealRevisionGatewayTest::test_transition_drives_a_freshly_submitted_instance_through_the_submit_event`
and `RealBulkTransitionGatewayTest` are the two tests that will validate
this once resolved — both are currently red for this reason alone (the
identity-argument-shape issue they were originally written to catch is
already fixed).

## Resolution: named enum constants, values pinned to the real BPMN/CMMN element ids

Confirmed by design, not a bug: the interpreter requires `current_state`
to hold the literal BPMN/CMMN element `id` from the authored XML. This
app's human-readable domain vocabulary (`manager_approval`,
`finance_review`, ...) was never a value the interpreter could recognize.

Rather than treating the XML element ids as strings to translate away
from, the app now treats them as a fixed, package-owned DSL vocabulary
and names each value with a backed PHP enum — `App\Enums\ExpenseReportState`
and `App\Enums\ExpenseDisputeState` — mirroring the existing
`App\Enums\UserRole` pattern already used elsewhere in this app. The
enum's *value* **is** the real XML id (e.g.
`ExpenseReportState::ManagerApproval->value === 'Task_ManagerReview'`);
application code reads/writes/compares the named case everywhere instead
of a bare string, closing the "hardcoded magic string" concern without
introducing a second, translated vocabulary.

`ExpenseReport::currentState()` now returns
`ExpenseReportState::from($this->instance->current_state)` instead of
the raw column value. `Instance::create()`/factory boundary sites pass
`->value` explicitly, since a single `instances.current_state` column
holds two different vocabularies (process vs. case) depending on `type`
and can't carry a single native enum cast.

This resolved both previously-red tests:

- `RealRevisionGatewayTest::test_transition_drives_a_freshly_submitted_instance_through_the_submit_event`
  confirmed the one inferential leap in this fix — a freshly created,
  not-yet-transitioned process instance's initial state is
  `Task_SubmitExpense` (the `submit` flow's `sourceRef`), matching
  `ExpenseReportState::SubmitExpense`. **Now green.**
- `RealBulkTransitionGatewayTest` used the already-confirmed
  `Task_ManagerReview` (`ExpenseReportState::ManagerApproval`). **Now green.**

`ExpenseDisputeState::Open` (`CasePlanModel_ExpenseDispute`) remains an
unverified guess — no real (non-faked) integration test exercises the
CMMN dispute flow yet, so nothing currently exercises this value against
the real interpreter. Flagged as a follow-up: add a real CMMN
integration test analogous to `RealRevisionGatewayTest` before relying
on this value in production.

## Resolution: `BpmnProcessModel::vocabulary()` closes item 3 of "what's missing" (BPMN only)

Item 3 above asked whether the package would ever expose valid
state/event ids per model revision instead of leaving the app to guess.
ADR-012 ("BPMN Vocabulary Hash") answers this for BPMN:
`BpmnProcessModel::vocabulary(): BpmnVocabulary` — obtained via the
existing `ModelDefinitionGateway::resolve()` boundary already used
elsewhere in this app — returns the model's real, deduplicated node ids
and sequence-flow names, plus a `hash()` for drift detection between
revisions.

`tests/Feature/Integration/RealBpmnVocabularyTest.php` uses this against
the real package (no fakes) to:

- assert every `ExpenseReportState` case's value is a real node id in
  `resources/bpm/expense-reimbursement.bpmn.xml` — upgrading the
  `SubmitExpense`/`Task_SubmitExpense` value from "confirmed by one
  transition test happening to reach it" to "checked directly against
  the model's full vocabulary," and covering the other four cases too,
  which no real-interpreter test exercises;
- assert every BPMN event-name literal hardcoded across the MCP tools
  and `EscalateOverdueExpenseReports` (`submit`, `escalate_to_finance`,
  `auto_approve`, `send_to_finance`, `reject`, `finance_reject`) is a
  real sequence-flow name;
- pin `vocabulary()->hash()` against a committed baseline, so a future
  edit to the `.bpmn.xml` that silently renames or removes a node id or
  flow name this app depends on fails this test loudly instead of
  surfacing as a runtime `RuntimeException` mid-transition.

Per ADR-012, this is BPMN-only — `ExpenseDisputeState::Open` and the
CMMN flow were still unaddressed by the package at the time this section
was written and remained the open follow-up noted above.

**Update:** `ExpenseDisputeState::Open`'s guessed value
(`CasePlanModel_ExpenseDispute`) has since been checked against the real
`CmmnParser`/`CmmnInterpreter` and was wrong — see
`docs/gap-analysis/cmmn-ad-hoc-case-plan-unsupported.md` for the full
resolution (the CMMN model also needed reshaping to match ADR-010, which
is what exposed this). Corrected to `PlanItem_GatherEvidence`, the real
start plan item, and confirmed by
`tests/Feature/Integration/RealOpenDisputeGatewayTest.php`.
