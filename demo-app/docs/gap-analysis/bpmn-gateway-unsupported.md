# Gap Analysis: `exclusiveGateway` Unsupported by the Real BPMN Interpreter

**Audience:** owner(s) of the arc42 docs site (`http://host.docker.internal:8000`)
and the `lobstar/bpm-engine` package repo.

**Raised by:** manual MCP Inspector testing of `RejectExpense` against
the real (non-faked) bindings — `RuntimeException: Unknown BPMN node
[Gateway_ManagerDecision]`.

## The gap

`resources/bpm/expense-reimbursement.bpmn.xml` modeled its three
decision points (manager approve/reject, auto-approval threshold,
finance approve/reject) as `exclusiveGateway` elements — standard BPMN
2.0 XOR-split/merge nodes. Calling `RevisionGateway::transition()` with
an event that had to route through one of them failed:

```
RuntimeException: Unknown BPMN node [Gateway_ManagerDecision].
```

ADR-011 ("BPMN Process Scope") states this is deliberate, documented
scope, not a bug: `BpmnParser`/`BpmnInterpreter` support only three flow
node types — `startEvent`, the task family (uniformly treated as a
generic task node), and `endEvent`. "No gateways (exclusive, parallel,
inclusive, complex, or event-based) — no branching or merging logic."
Branching is expressed instead by giving a single task multiple
outgoing sequence flows, each with a distinct `name`, matched against
the triggering event by exact string equality (an unnamed flow is the
fallback when no name matches). `conditionExpression` is never read.

## Why it's load-bearing, not cosmetic

Every existing test covering `RejectExpense`/`ApproveExpense`/
`EscalateToFinance` uses `UsesFakeBpmGateways`, whose fakes never parse
the XML at all — so the gateways' incompatibility with the real parser
was invisible until manual MCP Inspector testing reached the real
interpreter (`docs/gap-analysis/model-revision-id-resolution.md` and
`docs/gap-analysis/cmmn-ad-hoc-case-plan-unsupported.md` document the
same pattern for two earlier gaps). All three gateways
(`Gateway_ManagerDecision`, `Gateway_ThresholdDecision`,
`Gateway_FinanceDecision`) were affected, so `reject`, `auto_approve`,
`send_to_finance`, `finance_approve`, and `finance_reject` were all
unreachable via the real package — only `submit` and `escalate_to_finance`
(which already happened to be direct named flows off a task, not routed
through a gateway) worked.

## Resolution: flattened gateways into direct named task flows

Unlike the CMMN ad hoc gap, this required no product decision: since
`conditionExpression` is never evaluated by the real interpreter, the
gateways in this model never carried any conditional logic of their
own — they were pure routers between named flows already chosen by
event name at the task level (`ApproveExpense` already picks
`auto_approve` vs. `send_to_finance` in application code before calling
`transition()`; `Task_EvaluateThreshold`, the `businessRuleTask` between
`Gateway_ManagerDecision` and `Gateway_ThresholdDecision`, was
confirmed to be purely diagrammatic — `AutoApprovalDecisionService`
evaluates the DMN model out of band via `DecisionGateway`, never parking
the instance at that node). Removing the three gateways and
`Task_EvaluateThreshold`, and re-pointing their outgoing edges directly
onto `Task_ManagerReview`/`Task_FinanceReview`, is behavior-preserving:
identical event vocabulary, identical destinations.

New shape: `Task_ManagerReview` now has four outgoing named flows
(`reject`, `auto_approve`, `send_to_finance`, `escalate_to_finance`);
`Task_FinanceReview` has two (`finance_approve`, `finance_reject`) —
exactly the same fan-out `Task_ManagerReview` already used successfully
for `escalate_to_finance` before this fix, just extended to the other
events.

`tests/Feature/Integration/RealBpmnGatewayFlatteningTest.php` exercises
all five previously-broken transitions against the real package and
asserts the resulting `current_state`. `RealBpmnVocabularyTest`'s pinned
hash was updated to match the new node/flow set (the same seven event
names it checks are all still present).
