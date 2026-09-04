# Gap Analysis: Expense Dispute's Ad Hoc CMMN Case Plan vs. ADR-010's Linear-Only Scope

**Audience:** owner(s) of the arc42 docs site (`http://host.docker.internal:8000`)
and the `lobstar/bpm-engine` package repo.

**Raised by:** `tests/Feature/Integration/RealOpenDisputeGatewayTest.php`,
written while fixing `docs/gap-analysis/model-revision-id-resolution.md`,
which is the first test to exercise `OpenDispute` against the real
(non-faked) `lobstar/bpm-engine` bindings. Reported per
`AGENT_INSTRUCTIONS.md`'s "Traceability" instruction rather than worked
around silently.

## The gap

`resources/bpm/expense-dispute.cmmn.xml` fails to parse at all under the
real package:

```
InvalidArgumentException: CMMN XML must have exactly one plan item with
no entry criterion to start from; found 4.

/package/src/Cmmn/CmmnParser.php:80
/package/src/Core/ModelRegistry.php:69
```

The file's `casePlanModel` has four `planItem` elements
(`PlanItem_GatherEvidence`, `PlanItem_Escalate`, `PlanItem_Interview`,
`PlanItem_Resolve`), none with an `entryCriterion` — by design, per the
file's own comment:

```
Ad hoc: these are discretionary items on the case plan, not a fixed
sequence — any applicable item may be actioned while the case is open,
in whatever order the situation calls for.
```

ADR-010 ("CMMN Case Plan Scope") documents the real `CmmnParser`/
`CmmnInterpreter` as deliberately scoped to something narrower: "a
single, flat `casePlanModel`... there is no discretionary item support
(nothing added to the plan at runtime)" and "exactly one designated
start: the plan item with no `entryCriterion`. If none or more than one
qualifies as a start candidate under this rule, `CmmnParser` throws
rather than guessing." The ADR is explicit that this is intentional, not
a gap in the package: "What's implemented is closer to 'CMMN syntax
driving a BPMN-shaped linear interpreter' than general case management;
that gap is real and intentional, not an oversight."

## Why it's load-bearing, not cosmetic

The scenario's Expense Dispute case was designed around real CMMN's ad
hoc semantics (discretionary items, no fixed order) — the shape CMMN is
actually meant for — but the package only implements a linear subset
that mirrors BPMN. **The two are not compatible by construction, not by
bug**: no amount of tweaking `Instance.current_state` naming or
`model_revision_id` resolution (the two gaps already fixed) will make
this XML parse, because the file's fundamental shape (four independent,
ungated tasks) is exactly what ADR-010 says `CmmnParser` rejects.

Every existing test covering `OpenDispute` (`OpenDisputeTest.php`) uses
`UsesFakeBpmGateways`, whose `FakeRevisionGateway`/`FakeModelDefinitionGateway`
never parse the XML at all — they only exercise this app's own
bookkeeping (dispute creation, authorization). This is why the CMMN
model's incompatibility with the real parser was never caught until a
real integration test existed. `App\Enums\ExpenseDisputeState::Open`
(`CasePlanModel_ExpenseDispute`), flagged as an unverified guess in
`docs/gap-analysis/bpmn-state-naming-convention.md`, is unverifiable as
things stand — the model can't be resolved by the real interpreter at
all, regardless of which node id is used as the initial state.

## What's specifically missing to close the gap

1. A product decision: does the Expense Dispute scenario actually need
   ad hoc/discretionary semantics, or can it be reshaped into ADR-010's
   linear subset (one designated start, `entryCriterion`/`sentry`-chained
   plan items) without losing anything the scenario depends on? This is
   a scenario-authoring call, not something to guess at from the package
   side.
2. If ad hoc semantics are actually required, that's a real, scoped
   feature gap in `CmmnParser`/`CmmnInterpreter` relative to this
   scenario's needs — worth its own ADR revisiting ADR-010's scope, not
   a silent workaround here.

## Where we looked and found nothing

Per this repo's cross-repo isolation policy (`CLAUDE.md`), we did not
read `CmmnParser.php` beyond the file:line and message that appeared
unprompted in the exception trace above, and did not read the package's
`AGENT_INSTRUCTIONS.md` or ADRs' internal planning notes. ADR-010 itself
(the arc42 docs site) was sufficient to confirm this is documented,
intentional scope — not an implementation bug to report upstream.

## Suggested resolution path

This is a scenario-design decision, not a code fix, so we stopped here
rather than rewriting `expense-dispute.cmmn.xml` into a forced linear
chain unilaterally — that would silently change the case's intended
business semantics (independent discretionary tasks) without
product/stakeholder sign-off. `tests/Feature/Integration/RealOpenDisputeGatewayTest.php`
is left red, naming this doc, until one of the two paths in "What's
specifically missing" is chosen.

## Resolution: reshaped into ADR-010's linear subset

Product decision: the scenario doesn't depend on true ad hoc ordering,
so `expense-dispute.cmmn.xml` was rewritten as a single linear chain —
`PlanItem_GatherEvidence` (the sole plan item with no `entryCriterion`,
now the designated start) → `PlanItem_Interview` → `PlanItem_Escalate` →
`PlanItem_Resolve`, each entered via an `entryCriterion`/`sentry`/
`planItemOnPart` chain on the prior item's `complete` event, exactly
ADR-010's documented mechanism. The file now parses under the real
`CmmnParser`.

This surfaced the exact next-layer issue this doc predicted:
`ExpenseDisputeState::Open` (`CasePlanModel_ExpenseDispute`) was
confirmed wrong by the real interpreter —
`RuntimeException: No transition for event [open_dispute] from state
[CasePlanModel_ExpenseDispute]` — since the interpreter's nodes are
plan items, not the `casePlanModel` container itself. Corrected to
`PlanItem_GatherEvidence`, the real start node.

That in turn revealed a structural fact, not a wording ambiguity:
ADR-010 defines the start plan item as *the one with no
`entryCriterion`* — so by construction, nothing can ever have a named
transition into it. `OpenDispute.php`'s explicit
`$this->revisionGateway->transition($caseInstance, 'open_dispute')`
call, made immediately after creating the instance, could never match
any real transition regardless of XML wording. Removed it — mirroring
`SubmitExpense`, whose instance similarly starts already-at its real
start task (`Task_SubmitExpense`, reached via an *unnamed* BPMN flow)
with no initiating `transition()` call needed. `App\Bpm\Contracts\RevisionGateway`
is no longer a dependency of `OpenDispute` at all now that case creation
needs no transition.

`tests/Feature/Integration/RealOpenDisputeGatewayTest.php` is green:
opening a dispute now sets a real `model_revision_id` and lands the
instance at `ExpenseDisputeState::Open` (`PlanItem_GatherEvidence`) via
the real, unstubbed package.
