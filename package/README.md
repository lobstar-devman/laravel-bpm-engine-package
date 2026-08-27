# BPM Engine (package)

The Composer package implementing the BPM Engine design (see the arc42
documentation site, below).

This directory is self-contained and relocatable — nothing in it (no
path, mount, or link) points outside itself, so it can be copied out
into its own git repository at any time (`cp -r implementation/
~/new-repo && cd ~/new-repo && git init`) with nothing to fix up. See
ADR-007 ("Implementation is self-contained and relocatable") on the docs
site for the full reasoning.

**Continuing the implementation?** See
[AGENT_INSTRUCTIONS.md](AGENT_INSTRUCTIONS.md) for what to read first
and the current milestone.

## Code ↔ docs traceability

The Building Block View (arc42 Section 5), Runtime View (Section 6), and
domain model (Section 8) are the design source of truth (per
`architecting-agent.md`'s living-system principle) that this code
implements. If implementing something reveals the design needs to
change — a method signature, a new component, a schema tweak — update
the relevant doc/source-of-truth file first, in the docs repo, then
adjust this code to match. Don't let the two silently drift apart.

Read the docs the normal way: in your editor, or via the docs-toolkit's
served site (`docker compose up` in the docs repo, then
`http://localhost:8000`) — not from inside this container. This
container has no bind-mount into the docs repo and needs none: both
Structurizr and Mermaid render to SVG, so every diagram's labels and
relationship text remain real, searchable text on the served pages, not
flattened into an image. See ADR-007 for the full reasoning.

## Status

Every class under `src/` is currently a stub — it establishes the API
surface implied by the docs (method signatures, dependencies between
components) but throws `RuntimeException('Not implemented yet.')`. Real
BPMN/CMMN/DMN parsing and interpretation logic is the next step.

## Commands

Run from the `implementation/` directory (one level above this one,
where `docker-compose.yml` lives):

```
docker compose build package
docker compose run --rm package composer install
docker compose run --rm package vendor/bin/pest
docker compose run --rm package vendor/bin/pint --test
docker compose run --rm package vendor/bin/phpstan analyse
docker compose run --rm package bash   # ad hoc shell
```
