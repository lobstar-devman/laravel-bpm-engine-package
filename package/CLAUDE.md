# Cross-repo agent isolation policy

This repo (`lobstar/bpm-engine`) is consumed by a sibling app, `demo-app`
(`/app`), as a local Composer path dependency. Agents working in this
repo must design and implement against this package's **own** arc42 docs
(served by the docs-toolkit) and ADRs — do not read `/app`'s
`AGENT_INSTRUCTIONS.md` or its application source to inform work here.

**Why:** `demo-app` exists to exercise this package the way a real
external consumer would, building only against the documented, stable
public signatures (arc42 Section 5). An agent here that peeks at
`demo-app`'s internals can end up shaping the package's implementation
around one consumer's incidental choices instead of the documented
contract — defeating the point of keeping them separate. If something
`demo-app` needs isn't in the docs, that's a documentation gap, not a
reason to read across the boundary — see this repo's own
`AGENT_INSTRUCTIONS.md` (Traceability) for how to report it.

The mirror-image policy lives in `/app/CLAUDE.md` for agents working on
the demo-app side.
