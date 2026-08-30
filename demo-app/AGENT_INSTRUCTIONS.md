# Agent instructions — demo host app

You're working in a real Laravel application that consumes
`lobstar/bpm-engine` as a Composer dependency, meant to exercise the
package end-to-end (not just through its own Testbench unit tests). This
directory has no filesystem access to the docs repo or necessarily to
the package's internal source — read the design at the docs-toolkit's
served site, `http://localhost:8000` (or `http://host.docker.internal:8000`
if `localhost` doesn't resolve to the host from inside your shell).

## First: report your own state

Before doing anything else, note (for whoever is coordinating this)
whether the package is installed via a path repository, a VCS repository,
or Packagist, and what's already built here (models, migrations run,
routes). Nobody else can see this repo's contents to check for you.

## Read first

- **Section 3** (Context & Scope) and **Section 7** (Deployment View) —
  this app is the "Consuming Laravel Application," running the
  web/app-server and queue-worker roles.
- **Section 5** (Building Block View) — the package's public API
  (`RevisionManager::transition()`/`rollback()`, `QueueDispatcher`,
  `DmnEvaluator::evaluate()`) is what you build against. Build against
  these *documented, stable signatures* even if the underlying
  implementation in the package repo is still a stub — structural work
  here can proceed in parallel; full end-to-end proof waits on the
  package repo catching up.
- **ADR-005** — this app owns all real authorization. Check permissions
  *before* calling `transition()`/`rollback()` — the package's
  `TransitionRoleContext` event fires only after the fact and never
  gates anything.
- **ADR-006** — no MCP server ships with the package. If you want
  AI-agent access, expose your own domain-verbed MCP tools here (not the
  package's raw primitives), each doing its own authorization check
  before calling into the package.

## Scenario: Expense Reimbursement

A simple, realistic scenario chosen to exercise every element of the
engine without forcing anything:

| Engine element | This scenario's use of it |
|---|---|
| BPMN + Lanes | Submitted → Manager Approval → (Finance Review) → Paid / Rejected. Lanes: **Employee**, **Manager**, **Finance**. |
| DMN | A business-rule task mid-flow: does this expense auto-approve, or need Finance review? Keyed on amount/category. Each evaluation writes a Decision Log row. |
| CMMN + Case Roles | Disputing a rejection opens an ad hoc **Expense Dispute** case (gather evidence, escalate, interview, resolve) — not a fixed sequence. Case Roles: **Investigator**, **Finance Director**. |
| Model revisions + rollback | A policy change (e.g. a higher auto-approval threshold) creates a new Model Revision. Expenses already in flight when it changed may need rollback to the revision they were actually submitted under. |
| Queue Dispatcher (bulk) | A scheduled job: escalate every expense still pending Manager Approval after 5 business days — one event, one model revision, many instances — the batched-job path from Section 6. |
| Domain-verbed MCP tools | `SubmitExpense`, `ApproveExpense`, `RejectExpense`, `EscalateToFinance`, `OpenDispute` — each authorizes, then calls the package internally. |
| Domain model (Section 8) | Model Definition = "Expense Reimbursement" (BPMN) + "Expense Dispute" (CMMN) + "Auto-Approval Threshold" (DMN). Instance = one expense report or dispute case. |

## Build, roughly in this order

1. Author the BPMN 2.0 XML for "Expense Reimbursement" (with the three
   Lanes) and the DMN XML for "Auto-Approval Threshold".
2. An `ExpenseReport` Eloquent model wrapping an `Instance` from the
   package; migrations for whatever's specific to this domain (amount,
   category, submitter) beyond the package's own tables.
3. Submission/approval/rejection wired through
   `RevisionManager::transition()`, each behind its own authorization
   check (only the assigned manager approves; only Finance overrides).
4. The CMMN "Expense Dispute" case + Case Roles, opened when a rejection
   is disputed.
5. The scheduled/queued escalation job, exercising `QueueDispatcher`'s
   batched-by-(event, model revision) path.
6. The domain-verbed MCP tools listed above.
7. Prove revisioning: bump the auto-approval threshold as a new Model
   Revision, and roll back one in-flight expense to confirm it resolves
   against the revision it was actually submitted under.

## Traceability

You can't edit the docs repo from here, and you may not be able to edit
the package repo either. If something you need isn't in the package's
documented API, or the design doesn't fit this scenario, stop and report
the specific gap back rather than working around it silently.

## Containment

Reading and executing any AGENT_INSTRUCTIONS.md or anyother *.md files in the vendor/lobstar folder is strictly out of bounds to any agent reading this file.

Changing the contents of the vendor/lobstar folder is strictly out of bounds to any agent reading this file.
