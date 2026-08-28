# Gap Analysis: Revision Resolution Semantics

**Audience:** owner(s) of the arc42 docs site (`http://host.docker.internal:8000`)
and the `lobstar/bpm-engine` package repo.

**Raised by:** the demo host app (`/app`), while building the Expense
Reimbursement scenario against the package's documented API, per
`AGENT_INSTRUCTIONS.md`'s "Traceability" instruction to report a
specific gap rather than work around it silently.

## The gap

Section 5 (Building Block View) and Section 6 (Runtime View) describe
`RevisionManager::transition()`/`rollback()` and `ModelRegistry::resolve()`
only in prose — no documented resolution algorithm states whether
`transition()` interprets an instance against its own stored
`model_revision_id` ("pinned") or always against the latest revision of
that model definition at call time ("floating").

## Why it's load-bearing, not cosmetic

Every `Instance` row has a `model_revision_id` FK. When `transition()`
is called, the engine must decide which revision's rules govern that
transition. Two designs are both plausible from the docs alone:

- **Pinned** — `transition()` always uses the instance's own stored
  `model_revision_id`. An instance never drifts off its original
  revision on its own.
- **Floating** — `transition()` ignores the stored revision and always
  resolves to the model definition's current latest revision.

These have opposite implications for this scenario's step 7 ("bump the
auto-approval threshold as a new Model Revision; roll back one in-flight
expense to confirm it resolves against the revision it was actually
submitted under"):

- Under **pinned** resolution, `rollback()` has no obvious job — nothing
  drifts, so nothing needs rolling back, unless some other undocumented
  "migrate forward" operation exists to move an instance onto a newer
  revision in the first place.
- Under **floating** resolution, `rollback()` is essential: publishing a
  new `auto_approval_threshold` revision would silently change the rules
  applied to every in-flight expense's next transition — including ones
  submitted before the change — unless explicitly rolled back to the
  revision they were actually submitted under.

The existence of `rollback()` as a method distinct from `transition()`
is suggestive of the floating model, but that alone isn't confirmation.

## New evidence found while investigating (not yet reflected in the docs site)

`RevisionManager`'s source docblock (`src/Core/RevisionManager.php`,
package repo) says `transition()` "Drives `$instance` through **its
current** model revision via `$event`," and `rollback()` "Rolls
`$instance` back to `$targetRevision`." This leans toward instances
having a mutable "current revision" pointer — closer to pinned-with-
explicit-mutation than to always-latest — but "current" is still
ambiguous between "the revision this instance is tracking" and
"whatever's presently in effect," and the docblock is prose on an
unimplemented stub, not a contract. It narrows the question; it doesn't
close it.

## What's specifically missing to close the gap

1. An explicit statement of the resolution algorithm in Section 5 —
   does `transition()` read `instances.model_revision_id`, or resolve
   the model key to its latest revision independent of that column?
2. The actual behavior of `ModelRegistry::resolve(string $key, ?int
   $revision = null): mixed` — when `$revision` is `null`, does it
   return the latest revision unconditionally, or is `null` never the
   code path `transition()` itself uses internally?
3. What `rollback()` actually mutates — does it write
   `instances.model_revision_id` directly, append a `TransitionEvent`
   that `resolve()` later reads, or something else? This determines
   what later `transition()` calls will observe.
4. Whether this decision warrants its own ADR — pinned-vs-floating is
   exactly the kind of cross-cutting, hard-to-reverse choice ADR-005
   and ADR-006 already exist to record.

## Where we looked and found nothing

- The live docs site's Section 5 class/ER Mermaid diagrams
  (`data-model/domain-model.mmd` and similar sources of truth) are still
  unpopulated placeholders.
- `RevisionManager`, `ModelRegistry`, and every other `Core`/`Bpmn`/
  `Cmmn`/`Dmn` class in the package repo is a stub whose method body is
  a single unconditional `throw new RuntimeException('Not implemented
  yet.')` — no partial logic, TODOs, or branching to infer intent from,
  beyond the docblock quoted above.

## Suggested resolution path

Once the package repo implements `RevisionManager`/`ModelRegistry`,
treat the real behavior as the source of truth: update Section 5/6 to
document the resolution algorithm explicitly, and add an ADR if the
choice was deliberate and consequential (it is). This demo re-runs
`tests/Feature/Expenses/PolicyRevisionTest.php` — currently passing
against a fake gateway that only proves the app's own bookkeeping — with
the real `Package*Gateway` bindings substituted in, as a contract check
against whichever behavior the implementation settles on.
